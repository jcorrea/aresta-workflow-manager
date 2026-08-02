# Manual: criando um workflow pelo editor visual

Guia de uso do editor visual (canvas de arrastar-e-conectar) — não é uma spec de implementação
(essas ficam em `docs/specs/`), é o "como usar" para quem vai desenhar um processo de negócio.
Todos os exemplos abaixo se referem a um workflow real já criado no sistema para servir de
referência: **"Aprovação de Compra"**, em `/workflows` → Aprovação de Compra → "Continuar editando
rascunho". Abra-o numa aba ao lado enquanto lê.

## 1. Chegando lá

Depois de logar, a tela inicial (`/`) tem um botão **"Ir para Workflows"**. De `/workflows`:

- **Novo workflow** cria um registro vazio e já abre direto no editor, numa versão `draft`.
- Clicar em **Ver** num workflow existente leva pra tela de detalhe (`Workflows/Show`), que mostra
  o histórico de versões publicadas e, se houver um rascunho em andamento, um botão **"Continuar
  editando rascunho"**. Se não houver rascunho mas já existir uma versão publicada, aparece
  **"Editar de novo"** — isso clona a versão publicada vigente como um novo rascunho e abre o
  editor nela (a versão publicada em si nunca é alterada, ver `docs/specs/01-modelo-de-dados.md`
  §2.2).

Só existe **um rascunho por workflow por vez** — não dá pra ter duas edições em paralelo do mesmo
processo.

## 2. Os dois blocos visuais: Etapa e Atividade

O canvas tem dois tipos de elemento, com afordance visual bem diferente pra não precisar de
legenda:

- **Etapa** (`WorkflowStep`): um retângulo grande de borda tracejada, com título no topo — é só um
  **agrupamento visual**, sem lógica de execução própria (não define ordem nem se comporta como
  "fase" obrigatória). Serve pra organizar visualmente atividades relacionadas (ex.: tudo que
  acontece na etapa "Aprovação" fica dentro do retângulo "Aprovação").
- **Atividade** (`WorkflowActivity`): um bloco menor dentro de uma etapa — é o nó que realmente
  participa do grafo de execução. Todo o resto deste manual gira em torno de atividades.

Botão **"+ Etapa"** no cabeçalho cria uma etapa vazia. Dentro de cada etapa tem um botão
**"+ atividade"** que cria uma atividade nova dentro dela (tipo `task` por padrão, sempre editável
depois).

**Arrastar uma etapa move o grupo inteiro** (as atividades dentro se movem junto); arrastar uma
atividade sozinha só move ela — inclusive pra fora da etapa atual, se você soltar sobre outra etapa
(reatribui ela pra lá).

## 3. Os quatro tipos de atividade

Ao clicar numa atividade, o painel lateral direito abre com um campo **Tipo** (dropdown). Os quatro
valores possíveis, cada um com um ícone fixo pra reconhecer o tipo de longe no canvas sem precisar
clicar:

| Ícone | Tipo | O que significa |
|---|---|---|
| 👤 | **Tarefa** (`task`) | Alguém (um papel ou uma pessoa específica) precisa fazer algo manualmente. Aparece na fila "Minhas tarefas" (`/inbox`) de quem for responsável. |
| 📝 | **Formulário** (`form`) | Igual à tarefa (tem responsável, aparece no inbox), mas além de "concluir" a pessoa preenche campos definidos por você — ver §5. |
| ⚡ | **Ação automática** (`automated_action`) | O sistema executa sozinho, sem esperar ninguém — webhook, e-mail ou geração de documento. Não tem responsável. |
| ◆ | **Decisão** (`condition`, formato de losango) | Bifurca o fluxo automaticamente com base numa condição — ver §6. Não tem responsável. |

No exemplo, "Preencher solicitação" é `form`, "Aprovar compra" é `task`, "Decisão" é `condition`, e
"Gerar pedido de compra"/"Notificar solicitante" são `automated_action`.

### Campos comuns a `task`/`form`

- **Responsável**: "Nenhum" / "Papel" / "Usuário". Se for "Papel", escolhe entre os papéis de
  negócio já cadastrados na organização (ex.: "Aprovador Financeiro" — esses são cadastrados fora
  do editor, no painel administrativo ou via API; o editor só escolhe entre os que já existem e
  estão ativos). Se for "Usuário", atribui a uma pessoa específica.
- **SLA (horas)**: prazo — usado pelas notificações de "prazo vencendo/vencido"
  (`docs/specs/04-integracao-e-notificacoes.md`), não bloqueia nada sozinho.

### Campo específico de `automated_action`

- **Ação**: Webhook / Enviar e-mail / Gerar documento. Se for Webhook, aparece um campo **URL**
  extra.

## 4. Início e fim

No painel lateral de uma atividade `task`/`form`, dois checkboxes: **Início** e **Fim**. Regras que
o validador exige antes de publicar (§7):

- Precisa de **pelo menos um** nó marcado como início.
- Precisa de **pelo menos um** nó marcado como fim.
- Pode ter **mais de um** de cada — no exemplo, "Preencher solicitação" é o único início, mas há
  **dois** fins ("Gerar pedido de compra" e "Notificar solicitante"), um pra cada desfecho possível
  da decisão.

Uma atividade `condition` marcada como fim termina o processo assim que é alcançada, sem precisar
de nenhuma transição de saída — mas isso é incomum; o normal é uma `condition` sempre ter saídas
(ver §6).

## 5. Formulário: definindo os campos

Atividades `form` guardam a lista de campos dentro do JSON de configuração — o editor visual atual
**não tem um construtor de campos por clique** ainda (isso é um item em aberto da spec, ver
`docs/specs/03-editor-visual.md`). Por ora, os campos de "Preencher solicitação" no exemplo foram
definidos programaticamente:

```json
{
  "fields": [
    { "key": "valor_proposta", "label": "Valor da compra (R$)", "type": "number", "required": true },
    { "key": "descricao", "label": "Descrição", "type": "text", "required": true }
  ]
}
```

Cada campo aparece pra quem for preencher em `/inbox` como um input (texto ou número, conforme
`type`), obrigatório se `required: true`. O valor preenchido fica disponível pras condições de
transição mais adiante no fluxo como `context.<key>` (ex.: `context.valor_proposta`).

## 6. Conectando atividades: transições

Passe o mouse sobre a borda de uma atividade — aparecem pontinhos verdes (handles) no topo e
embaixo. **Arraste de um ponto até outro nó** pra criar uma transição (aresta) entre eles; ela já é
salva na hora (uma chamada ao backend por conexão, sem precisar de botão "salvar").

Clicar numa transição já existente abre o mesmo painel lateral, com:

- **Rótulo**: texto livre que aparece ao lado da linha no canvas (ex.: "Aprovado").
- **Tipo**: "Sempre (paralelo/sequencial)" ou "Condição".

### Transição "Sempre"

Sai sem condição — sempre é seguida. **Se um nó tiver duas ou mais transições "Sempre" saindo
dele, isso é um fork paralelo**: as duas ativam ao mesmo tempo (decisão fechada,
`docs/specs/00-visao-geral.md` — join é sempre AND, nunca configurável). No exemplo,
"Preencher solicitação → Aprovar compra" e "Aprovar compra → Decisão" são assim, porque são passos
sequenciais únicos, não uma bifurcação.

### Transição "Condição"

Usada pra bifurcar de verdade — normalmente saindo de um nó `condition` (losango), mas tecnicamente
qualquer atividade pode ter uma saída condicional. Ao escolher "Condição", aparecem três campos:

- **Campo**: o dado a comparar (ex.: `result.aprovado`, ou `context.valor_proposta` se vier de um
  formulário anterior).
- **Operador**: `=`, `≠`, `>`, `≥`, `<`, `≤`.
- **Valor**: o que comparar.

No exemplo, o nó "Decisão" tem duas saídas condicionais: `result.aprovado = true` → "Gerar pedido de
compra" (rótulo "Aprovado"), e `result.aprovado != true` → "Notificar solicitante" (rótulo
"Reprovado"). **Combinar várias condições numa mesma transição (E/OU) ainda não tem builder visual**
— por ora é só campo único; combinações mais complexas exigem editar via API
(`docs/specs/03-editor-visual.md` §5, item em aberto).

## 7. Avisos e erros antes de publicar

Logo abaixo do cabeçalho, uma faixa lista os problemas encontrados no rascunho:

- **⛔ vermelho = erro**, bloqueia o botão "Publicar" até ser corrigido (ex.: "nenhum nó inicial",
  "atividade sem responsável", "decisão sem nenhuma transição de saída").
- **⚠️ amarelo = aviso**, não bloqueia, só chama atenção (ex.: "nó sem nenhuma conexão de entrada —
  pode estar em construção").

A validação mais elaborada é sobre **fork/join bem-formado**: se você criar um fork (duas ou mais
transições "Sempre" saindo do mesmo nó), o sistema exige que os ramos convirjam de novo, sem vazar
pra fora do bloco nem se misturar com outro fork — c.f. `docs/specs/02-motor-de-execucao.md` §3.3.
Isso só importa pra forks reais (paralelismo); o exemplo deste manual usa só decisão condicional
(XOR), que não entra nessa regra.

## 8. Publicando

Com zero erros (avisos não impedem), o botão **Publicar** cria a versão `published` — a partir daí
ela fica congelada (`docs/specs/01-modelo-de-dados.md` §2.2): instâncias que já estavam rodando
continuam na versão antiga, novas instâncias passam a usar a nova. Publicar volta você pra tela de
detalhe do workflow (`Workflows/Show`), que agora mostra a versão nova no histórico. Pra editar de
novo depois de publicado, use "Editar de novo" (clona um rascunho novo a partir da versão vigente).

## 9. O que acontece depois de publicado

Um processo publicado vira executável — ver `/instances` pra abrir/acompanhar instâncias reais
rodando sobre ele, e `/inbox` pra ver/executar as tarefas atribuídas ao usuário logado. Isso é
assunto de outro manual; este cobre só a modelagem visual.
