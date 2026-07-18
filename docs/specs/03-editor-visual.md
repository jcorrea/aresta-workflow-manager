# Spec: editor visual (canvas de arrastar-e-conectar)

Status: proposta, ainda não implementada. Depende de `00-visao-geral.md`, `01-modelo-de-dados.md` e
`02-motor-de-execucao.md`.

## 1. Por que não Filament, nem formulário tabular

O legado resolve isso com um formulário HTML tabular com 20 etapas × 5 atividades pré-alocadas em
branco (`00-visao-geral.md` §3.5) — sem nenhuma noção de posição, sem visualizar o fluxo como grafo.
Filament (usado no resto do produto para CRUD administrativo — organizações, papéis) também não é
adequado aqui: é uma ferramenta de formulários/tabelas, não de canvas livre com nós arrastáveis e
conexões desenhadas à mão. Por isso o editor visual é a única parte do produto fora do Filament,
construída com Vue 3 + Inertia.

## 2. Biblioteca de canvas: Vue Flow

**Vue Flow** (`@vue-flow/core`, + pacotes complementares `@vue-flow/controls`,
`@vue-flow/minimap`, `@vue-flow/background`) — equivalente em Vue 3 ao React Flow. Motivos:

- Suporta nós customizados via componentes Vue (necessário para renderizar `WorkflowActivity` com
  ícone por `type`, nome, responsável, badge de SLA).
- Suporta agrupamento de nós em "parent nodes" — mapeia diretamente para `WorkflowStep` como
  container visual de `WorkflowActivity` (arrastar o grupo move as atividades dentro; arrastar uma
  atividade pode reatribuí-la a outro grupo).
- Conexões (`edges`) com validação customizável via callback (`isValidConnection`) — usado para
  impedir, por exemplo, conectar duas atividades de versões diferentes, ou (dependendo da decisão
  final sobre ciclos) alertar visualmente sem bloquear.
- Suporta `edge labels` nativamente — usado para exibir o `label` de `workflow_transitions` (ex.:
  "Aprovado"/"Reprovado") diretamente na aresta.
- Ativamente mantida, TypeScript-first, boa documentação — baixo risco de abandono.

Alternativas descartadas: **jsPlumb**/**drawflow** (mais antigas, API imperativa, pouca integração
nativa com Vue reativo); **mxGraph/drawio embutido** (peso e complexidade de uma ferramenta de
diagramação genérica, não pensada para o caso de uso "grafo de workflow" especificamente); nenhuma
delas tem o equivalente do "parent node" pronto, que é o que resolve o agrupamento
etapa↔atividades sem trabalho extra.

## 3. Persistência: lógica do grafo vs. layout visual

Separação clara (evita acoplar posição de tela com dados de negócio):

- **Lógica do grafo** (o que o motor de execução consome): `workflow_steps`, `workflow_activities`
  (exceto `position_x`/`position_y`), `workflow_transitions` — persistidos via requests Inertia
  normais (`POST`/`PATCH` para os controllers de `WorkflowVersion`).
- **Layout visual**: `position_x`/`position_y`/`width`/`height` em `workflow_steps` e
  `workflow_activities`, mais `workflow_versions.canvas_json` para viewport (zoom/scroll inicial) —
  atualizados via um endpoint dedicado, debounced no client (não gravar a cada pixel de drag; salvar
  ~500ms depois que o usuário solta o nó, ou em lote ao clicar "salvar").

Fluxo de edição: o editor carrega a `WorkflowVersion` (deve ser `draft` — só existe uma por
`Workflow` por vez, `01-modelo-de-dados.md` §2.2) via Inertia com todos os steps/activities/
transitions já no formato de nós/arestas do Vue Flow (transformação feita no backend, num método
tipo `WorkflowVersion::toGraphPayload()`), e cada mutação do usuário (adicionar nó, conectar aresta,
renomear, mudar tipo, mover) dispara uma chamada — proposta: **auto-save otimista** (grava a cada
mutação estrutural relevante, com debounce só para posição), com indicador de "salvo"/"salvando" no
canto da tela, em vez de um botão "Salvar" explícito que arrisca perda de trabalho. Publicar
(`draft` → `published`) continua sendo uma ação explícita e separada — assim como **rollback**
(reativar uma versão antiga já publicada), que não é uma ação do canvas em si, mas da tela de
listagem/histórico do `Workflow` (`01-modelo-de-dados.md` §2.2.1/§2.2.2), já que não envolve editar
nó nenhum.

## 4. Tipos de nó (`WorkflowActivity.type`) — afordance visual

Cada tipo tem um componente de nó Vue Flow próprio (cor/ícone consistente, sem exigir que o usuário
leia uma legenda para entender o que é):

- **`task`** (tarefa manual): ícone de pessoa, mostra o papel/usuário responsável e o SLA no corpo do
  nó.
- **`form`** (coleta de dados): ícone de formulário, mostra quantos campos tem (ex.: "3 campos") —
  ao clicar, abre painel lateral (não modal bloqueante) para editar o schema de campos (ver
  `01-modelo-de-dados.md` §4).
- **`automated_action`** (ação automática): ícone de raio/engrenagem, mostra qual ação (webhook,
  e-mail, gerar documento) resumida no corpo do nó.
- **`condition`** (decisão): formato de losango (convenção universal de fluxograma para decisão,
  reconhecível mesmo sem treinamento em BPMN), sem responsável — as arestas que saem dele exibem o
  `label`/condição resumida.

Grupo `WorkflowStep`: renderizado como um "parent node" com fundo levemente destacado e título,
contendo os nós de atividade dentro — visualmente comunica "essas atividades pertencem à mesma
etapa" sem impor ordem sequencial rígida entre grupos (alinhado à decisão de DAG, §2.7 de
`00-visao-geral.md`).

## 5. Builder visual de condição (para nós `condition`)

Em vez de expor o JSON de `condition_expression` (`02-motor-de-execucao.md` §4) como texto, a UI
oferece, ao clicar numa aresta que sai de um nó `condition`:

- Um seletor de campo (dropdown com os campos conhecidos do `context`/`result` — populado a partir
  dos `form` anteriores no grafo, quando alcançável, e de variáveis conhecidas do processo).
- Um seletor de operador (`=`, `≠`, `>`, `<`, `≥`, `≤`, "contém").
- Um campo de valor (input tipado conforme o campo escolhido — número, texto, booleano).
- Botão "adicionar condição" para compor `all`/`any` (com um toggle "E"/"OU" entre elas).

Isso mantém a promessa do item 4 de `00-visao-geral.md` §2 (usuário de negócio, não desenvolvedor) sem
exigir que ele escreva nem leia uma expressão textual.

## 6. Validação de grafo antes de publicar

Antes de permitir `draft → published`, o backend valida (e o editor visual deve sinalizar
visualmente os problemas antes mesmo do usuário tentar publicar, com um painel "N problemas
encontrados" persistente):

- Existe exatamente ≥1 nó `is_start = true`.
- Existe ≥1 nó `is_end = true`, alcançável a partir de todo nó `is_start` (nenhum nó "solto" sem
  caminho até o fim).
- Todo nó `condition` tem ≥1 transição de saída (senão o motor prenderia a instância ali — ver
  `02-motor-de-execucao.md` §3.2).
- Todo nó `task`/`form` tem `assignee_type` preenchido (senão a atividade nasceria sem responsável e
  sem fila possível), e se `assignee_type = role`, o `assignee_role_id` referenciado aponta para um
  role com `active = true` (`01-modelo-de-dados.md` §2.6.1) — só se aplica ao **publicar o draft**;
  uma versão já publicada anteriormente nunca é revalidada, então um role desativado depois de já
  estar numa versão publicada não invalida nada retroativamente (§2.6.1, regra 2). Se um role
  referenciado pelo draft for desativado enquanto ainda está sendo editado, a publicação fica
  bloqueada até o editor trocar para um role ativo.
- Nós sem nenhuma conexão de entrada nem `is_start = true` são inatingíveis — alertar (não bloquear
  necessariamente, pode ser um nó em construção), mas deixar claro visualmente (ex. borda tracejada
  cinza).
- **Todo par fork/join é "bem formado"** (regra detalhada em `02-motor-de-execucao.md` §3.3.1: todo
  nó com ≥2 saídas `always` tem um único nó de junção correspondente, alcançado por exatamente um
  caminho por ramo, sem vazar nem se sobrepor a outro bloco paralelo). Esta é a validação mais
  computacionalmente pesada da lista (análise de pós-dominância sobre o grafo, feita por um serviço
  dedicado `WorkflowGraphValidator`) e a mais importante: sem ela, o AND-join do motor de execução
  poderia travar uma instância em produção esperando uma chegada que nunca acontece — ver a
  motivação completa em `02-motor-de-execucao.md` §3.3. Quando essa regra falha, destacar em
  vermelho tanto o fork quanto o(s) nó(s) que quebram o pareamento (não só um "erro genérico"), para
  o usuário conseguir corrigir sem precisar entender a teoria de grafos por trás.

## 7. Modo somente-leitura (reuso do mesmo componente para acompanhar instâncias — fase 6)

O mesmo componente Vue Flow é reaproveitado, em modo não-editável, para visualizar uma
`ProcessInstance` em andamento: nós já concluídos coloridos (ex. verde), nó(s) ativo(s) destacados
(ex. pulsando/borda animada), nós ainda não alcançados em cinza, arestas realmente percorridas
(conforme `process_instance_transition_logs`) destacadas em vs. arestas do desenho não percorridas
em cinza claro. Esse é o principal diferencial em relação ao legado, que só mostra uma barra de
progresso linear por etapa (`00-visao-geral.md` §3.5) — aqui o usuário literalmente vê por onde o
processo passou no grafo.

## 8. Itens em aberto

- Confirmar com o usuário, ao ter uma versão navegável, se auto-save otimista (§3) é aceitável ou se
  prefere um botão "Salvar" explícito — é uma escolha de UX com trade-off real (perda de trabalho vs.
  sensação de controle).
- O builder de condição (§5) populado a partir de "campos conhecidos" exige rastrear todos os
  formulários alcançáveis antes de um nó `condition` no grafo — isso é uma travessia não-trivial
  quando há ciclos; para o MVP, considerar simplificar para "todos os campos de todos os `form` da
  versão", sem análise de alcançabilidade, e refinar depois se causar confusão.
- **Timing da validação de fork/join (§6)**: decidir se o `WorkflowGraphValidator` roda só ao
  tentar publicar (mais simples, feedback tardio) ou também de forma incremental enquanto o usuário
  edita (feedback imediato ao conectar uma aresta problemática, melhor UX mas exige rodar a análise
  de pós-dominância a cada mutação do grafo — para processos grandes pode exigir debounce). Proposta
  para o MVP: só ao publicar; medir se isso incomoda na prática antes de investir em validação ao
  vivo.
