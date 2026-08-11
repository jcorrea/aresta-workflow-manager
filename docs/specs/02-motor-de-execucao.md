# Spec: motor de execução (WorkflowEngine)

Status: proposta, ainda não implementada. Depende de `00-visao-geral.md` e `01-modelo-de-dados.md`.

## 1. Objetivo

Substituir a "máquina de estados" implícita do legado (ordenação numérica + magic number
`idprocessdestiny === 4`, com as regras condicionais desabilitadas — ver `00-visao-geral.md` §3.3)
por um serviço explícito (`WorkflowEngine`) que avalia o grafo (`workflow_activities` +
`workflow_transitions`) de forma determinística e testável, suportando fork/join e condições reais.

## 2. Ciclo de vida de uma instância

1. **Início** (`WorkflowEngine::start(Workflow $workflow, array $context, User $startedBy)`):
   - Resolve `workflow.current_published_version_id` (erro se não houver versão publicada).
   - Cria `ProcessInstance` (`status = running`, `context` = payload inicial).
   - Localiza o(s) nó(s) `is_start = true` da versão e chama `activateActivity()` para cada um
     (normalmente um só, mas o modelo permite múltiplos pontos de entrada).
2. **Ativar uma atividade** (`activateActivity(ProcessInstance, WorkflowActivity)`):
   - Cria `ProcessInstanceActivity` (`status = pending` ou `in_progress` dependendo do tipo).
   - Resolve responsável (ver `01-modelo-de-dados.md` §3.4) para `type = task`/`form`.
   - Para `type = automated_action`: executa a ação imediatamente (síncrono para webhook/e-mail
     simples, ou despachado para uma queue job do Laravel se a ação puder ser lenta) e, ao concluir,
     chama `completeActivity()` automaticamente.
   - Para `type = condition`: avalia imediatamente (não espera humano) — ver §3.2.
   - Dispara notificação (in-app/e-mail) se houver responsável humano — ver
     `04-integracao-e-notificacoes.md` §3.
3. **Concluir uma atividade** (`completeActivity(ProcessInstanceActivity, array $result)`):
   - Marca `status = completed`, grava `result`/`form_data`, `completed_at`.
   - Mescla `$result` em `ProcessInstance.context` (`array_merge`, chave de campo escolhida por
     quem desenhou o processo — colisão de nome entre atividades é responsabilidade de nomear
     campos com chaves únicas, igual variável em qualquer linguagem). Sem isso, um nó `condition`
     dedicado (ex.: um nó de "Decisão" separado logo depois de um `form`/`task`) não teria como
     enxergar a resposta de quem o antecede: `condition` se autocompleta com `result = []` (§2,
     item 2) e só tem acesso a `context` — nunca ao `result` de quem o ativou. Decisão registrada
     (2026-08-08): `context` acumula ao longo da execução, não é só o payload inicial — ver
     `01-modelo-de-dados.md` §3.1.
   - Se `workflow_activity.is_end`: marca `ProcessInstance.status = completed`, `completed_at`, para
     de propagar (o merge acima acontece antes dessa checagem, então `context` fica completo mesmo
     na última atividade).
   - Senão, chama `advance()` — ver §3.

## 3. `advance()` — avaliação de transições (o coração do motor)

Substitui `Tasks::WorkflowType()`/`AddNextTask()` do legado. Busca todas as `workflow_transitions`
com `from_activity_id` = atividade concluída, ordenadas por `sort_order`:

### 3.1 Fork (paralelo) — transições `condition_type = always`

Se houver **mais de uma** transição `always` saindo do nó, **todas** são seguidas — cada uma ativa
sua `to_activity_id` independentemente (fork AND). Ex.: ao aprovar um orçamento, disparar em
paralelo "gerar contrato" e "notificar financeiro".

### 3.2 Decisão (XOR) — transições `condition_type = expression`

Avaliadas em ordem de `sort_order`; a **primeira** cuja `condition_expression` seja verdadeira é
seguida, e as demais são ignoradas — semântica de gateway exclusivo (XOR), igual a um
`if/elseif/else`. `field` resolve contra duas fontes, na avaliação de uma transição saindo de `X`:
`result.*` é o `result` da própria ativação de `X` que está sendo concluída agora (só existe pra
transições que saem direto de `X`, não sobrevive além do próximo nó); `context.*` é
`ProcessInstance.context` acumulado — inclui o payload inicial da instância **e** o `result` de
toda atividade já concluída no caminho até aqui (§2, item 3). Na prática: uma condição que só olha
o resultado da atividade imediatamente anterior pode usar `result.*` **ou** `context.*` (equivalem
nesse caso); uma condição num nó `condition` dedicado, alcançado por uma transição `always` (não
tem `result` próprio relevante — o dele é sempre `[]`), só enxerga `context.*`. Se
nenhuma bater e não houver uma transição "padrão" (proposta: uma transição `always` pode coexistir
como fallback, desde que seja a última em `sort_order`), a instância fica presa nesse nó — o motor
deve logar/notificar isso como um erro de configuração do processo (fluxo mal desenhado), não falhar
silenciosamente.

### 3.3 Join (convergência) — nó com múltiplas transições de entrada

**Decisão (2026-07-16)**: todo nó com mais de uma transição de entrada é um **AND-join** — não
existe OR-join no produto, nem como opção configurável por nó. Isso só é seguro porque o editor
visual **proíbe, no desenho, qualquer bloco paralelo mal-formado** que pudesse levar a uma junção
esperando eternamente por um caminho que nunca chega (deadlock) — ver §3.3.1 para a regra de
validação e `03-editor-visual.md` §6 para onde ela se encaixa no fluxo de publicação. Essa garantia
de "bem-formado" é o que permite o motor em si ser trivial: **não precisa de nenhuma análise de
grafo em tempo de execução**, só contar chegadas.

#### 3.3.1 Por que precisa de validação estrutural (o problema do AND-join "ingênuo")

Um AND-join "ingênuo" (ativa quando todos os predecessores diretos da versão completaram) trava a
instância para sempre se um dos predecessores está atrás de uma **decisão (XOR)** que, para aquela
instância, escolheu não seguir por ali — o motor ficaria esperando uma chegada que nunca vai
acontecer, porque a atividade correspondente nunca chega a ser criada. Isso só é impossível de
acontecer se todo par fork/join do grafo for **"bem formado"**: cada fork tem exatamente um join
correspondente, e toda decisão (XOR) que aparece dentro de um ramo do fork reconverge (tem seu
próprio join, ou termina o processo) **antes** de alcançar o join externo — nunca "vaza" pra fora do
bloco nem cruza com outro bloco paralelo de forma sobreposta.

Essa é a mesma ideia usada por ferramentas de BPM (ex.: verificação de "soundness" via decomposição
em regiões *single-entry-single-exit*, técnica de origem em análise de compiladores — pós-
dominância de Lengauer-Tarjan) para garantir que um processo com gateways paralelos e condicionais
misturados não tenha caminhos que travam. Não é um problema exclusivo deste produto nem uma
invenção desta spec — é o motivo de a decisão ter sido "validar no editor" em vez de "resolver de
forma esperta em tempo de execução": tentar ser esperto em runtime (ex.: adivinhar que um
predecessor "nunca vai chegar" e seguir sem ele) é exatamente a categoria de bug que esse tipo de
motor mais sofre historicamente.

Regra de validação implementável (rodar ao tentar publicar uma versão, e idealmente também como
aviso ao vivo no canvas — ver `03-editor-visual.md` §6):

1. Um nó é **fork** se tem ≥2 transições de saída `always`. Um nó é **join** se tem ≥2 transições de
   entrada (de qualquer tipo).
2. Para cada fork `F`, calcular seu **pós-dominador imediato** — o nó por onde *todo* caminho
   partindo de `F` obrigatoriamente passa (algoritmo padrão de análise de fluxo de controle, custo
   quase-linear no tamanho do grafo).
3. O par `(F, J)` é válido (bloco "bem formado") somente se: o pós-dominador de `F` é um join `J`, e
   `J` tem exatamente uma transição de entrada por ramo de saída de `F` (nem mais, nem menos), e
   nenhum caminho entre `F` e `J` sai desse conjunto de nós antes de chegar em `J`.
4. Blocos podem se aninhar livremente (um fork/join dentro de um ramo de outro fork/join), mas nunca
   se sobrepor parcialmente — dois blocos precisam estar completamente um dentro do outro ou
   completamente separados.
5. Qualquer fork sem par válido, ou qualquer join alcançado por caminhos que não vêm todos do mesmo
   fork válido, é reportado como erro de configuração do processo, apontando o(s) nó(s) específico(s)
   — a versão não pode ser publicada enquanto isso não for corrigido.

Isso é trabalho suficientemente não-trivial (mas bem documentado na literatura de BPM/compiladores)
para justificar um serviço dedicado (`WorkflowGraphValidator`) na fase 3 (editor visual), separado do
`WorkflowEngine` da fase 2 — o motor de execução em si (§3.3.2) não depende dessa análise, só se
beneficia da garantia que ela produz.

#### 3.3.2 Mecânica de runtime (trivial, graças à validação)

Como a validação de publicação já garante que todo join tem exatamente as chegadas esperadas
definidas estaticamente (uma por transição de entrada, sempre), o `advance()` não precisa calcular
nada sobre o grafo — só contar, por instância:

- Ao seguir uma transição `T` cujo destino é um join `J`: gravar o log de transição (já previsto em
  `process_instance_transition_logs`, ver `01-modelo-de-dados.md` §3.5) e então contar quantas
  transições de entrada **distintas** de `J` já têm log registrado para esta instância.
- Se esse número for igual ao total de transições de entrada de `J` (contagem estática, direto de
  `workflow_transitions`) → ativar `J` (`activateActivity`).
- Senão → não faz nada; a próxima transição irmã a completar refaz essa mesma contagem.

**Ciclos**: se o bloco fork/join estiver dentro de um laço de repetição (§3.4), a contagem de
chegadas deve considerar só os logs registrados **depois da conclusão mais recente do próprio fork
`F` nesta instância** (janela de tempo a partir de `completed_at` da última ativação de `F`) — senão
uma segunda passagem pelo bloco poderia contar chegadas de uma passagem anterior e ativar `J` cedo
demais. Não é necessária nenhuma tabela nova para isso: é só um filtro por `transitioned_at` na
consulta sobre `process_instance_transition_logs` já existente.

### 3.4 Ciclos

O grafo permite ciclos (ex.: "retrabalho" volta para uma atividade anterior) — diferente do legado,
que só tinha `Revoke()`/`Reopen()` como mecanismo manual e destrutivo (deletava tasks posteriores
via SQL direto). Com o motor baseado em grafo, um ciclo é só mais uma `workflow_transition` apontando
"para trás"; cada passagem pelo nó gera um novo `ProcessInstanceActivity` (histórico preservado, ao
contrário do `DELETE` do legado). O editor visual (`03-editor-visual.md`) deve alertar
visualmente quando o usuário desenha um ciclo, mas não bloquear — é um padrão legítimo de
retrabalho/aprovação.

## 4. Avaliação de `condition_expression`

Formato estruturado (JSON), não texto livre com `eval` — dado que quem desenha o processo é
administrador de negócio (`00-visao-geral.md` §2, item 4), não desenvolvedor. Proposta mínima (builder
tipo "regra simples"):

```json
{
  "field": "context.valor_proposta",
  "operator": ">",
  "value": 50000
}
```

com suporte a composição via `all`/`any`:

```json
{
  "any": [
    { "field": "result.aprovado", "operator": "=", "value": true },
    { "field": "context.cliente_vip", "operator": "=", "value": true }
  ]
}
```

Avaliador implementado como um serviço simples (`ConditionEvaluator`) que percorre esse JSON
recursivamente — **não** usar `symfony/expression-language` ou equivalente com sintaxe textual, para
não expor uma linguagem de expressão a um usuário não-técnico nem abrir superfície de
injeção/avaliação arbitrária. `field` sempre referencia um caminho dentro de `context` (variáveis do
processo, acumuladas ao longo da execução — §3.2) ou `result` (saída da atividade que está sendo
concluída agora), nunca código arbitrário.

Comparação usa `==`/`!=`/`>`/`>=`/`<`/`<=` soltos do PHP (sem cast explícito) — armadilha conhecida:
como o builder visual só tem campo de texto pra "Valor" (`03-editor-visual.md`), evitar usar a
string literal `"false"` como valor de comparação contra um resultado booleano, porque em PHP
`false == "false"` é `false` (string não vazia é *truthy* na conversão) — preferir valores de texto
sem ambiguidade booleana (ex.: `"aprovado"`/`"recusado"`) em vez de `"true"`/`"false"` pros campos
que alimentam decisão.

## 5. SLA / prazos

- `workflow_activities.sla_hours` (se definido) grava `process_instance_activities.due_at =
  started_at + sla_hours` no momento da ativação.
- Um job agendado (`php artisan schedule:run`, rodando de hora em hora) varre
  `process_instance_activities` com `status in (pending, in_progress)` e `due_at < now()` ainda não
  notificadas, disparando notificação de "prazo vencido" (ver `04-integracao-e-notificacoes.md` §4).
  Não bloqueia a atividade (o legado também não bloqueava por prazo) — é informativo/gerencial, para
  a fase 1; bloquear avanço por SLA fica como possível fase futura, fora do escopo inicial.

## 6. Testes (o motor deve ser testável sem UI)

Como a fase 2 do roadmap (`00-visao-geral.md` §8) implementa o motor **antes** do editor visual, os
testes de `WorkflowEngine` devem montar grafos diretamente via factories (`WorkflowVersion` +
`WorkflowActivity` + `WorkflowTransition`), sem depender do canvas:

- Fluxo linear simples (A → B → C, C `is_end`): completar A ativa B, completar B ativa C, completar
  C encerra a instância.
- Fork: A → (B, C) via duas transições `always` — completar A ativa B e C simultaneamente.
- Decisão: A → B se `valor > 50000`, senão A → C — testar os dois ramos.
- Join: (B, C) → D — D só ativa depois que B **e** C completarem, testando as duas ordens de
  conclusão (B antes de C, e C antes de B) para garantir que o resultado independe da ordem.
- Ciclo: A → B → A (condicional) → ... → fim — garantir que cada passagem gera um novo
  `ProcessInstanceActivity` e que o log de transições (`process_instance_transition_logs`) registra
  cada passagem, não só a última.
- Ciclo contendo um bloco fork/join: (fork F → B, C → join J) dentro de um laço que repete o bloco
  inteiro duas vezes — garantir que a segunda passagem não ativa `J` prematuramente contando chegadas
  da primeira passagem (janela por `completed_at` mais recente de `F`, ver §3.3.2).
- Ação automática: nó `automated_action` com `action = webhook` — testar com `Http::fake()` (mesmo
  padrão já usado no GIITS Status para testar integração com GitHub), garantindo que a conclusão
  dispara `advance()` sem intervenção humana.

### 6.1 O motor confia que o grafo é bem formado — a validação é responsabilidade do editor (fase 3)

Os testes de `WorkflowEngine` da fase 2 constroem grafos **já válidos** via factories (não passam
pelo `WorkflowGraphValidator`, que só existe a partir da fase 3). Isso é intencional: a garantia de
"todo join é alcançável de forma bem formada" é responsabilidade exclusiva da validação de
publicação (`03-editor-visual.md` §6), não do motor de execução — ver §3.3.1. Os testes do
`WorkflowGraphValidator` em si (grafos malformados sendo corretamente rejeitados) pertencem à
suíte da fase 3, não a esta.

Como nenhuma via de entrada além do editor visual existe para criar `WorkflowVersion` no MVP, isso é
seguro. Se uma fase futura expuser criação de processo via API (não prevista em
`04-integracao-e-notificacoes.md` — a API ali só cobre iniciar/consultar instâncias, não desenhar
templates), essa via também precisará rodar o `WorkflowGraphValidator` antes de permitir publicar,
pelo mesmo motivo.
