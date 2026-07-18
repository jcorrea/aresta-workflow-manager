# Spec: modelo de dados

Status: proposta, ainda não implementada. Depende de `00-visao-geral.md`.

Convenção: nomes de tabela em `snake_case` plural, como padrão Laravel/Eloquent (diferente do
legado, que usa nomes sem underscore tipo `processconfigsteps`).

## 1. Visão geral das entidades

```
Organization (empresa-cliente) ─┬─< User (via organization_user)
                                 ├─< Role (papel de negócio)
                                 ├─< Workflow ─< WorkflowVersion ─┬─< WorkflowStep ─< WorkflowActivity ─┬─< WorkflowTransition (from)
                                 │                                │                                     └─< WorkflowTransition (to)
                                 │                                └─< (canvas_json: posições x/y)
                                 │
                                 └─< ProcessInstance (aponta pra 1 WorkflowVersion) ─┬─< ProcessInstanceStep
                                                                                      └─< ProcessInstanceActivity ─< ProcessInstanceTransitionLog
```

Dois "lados" claros, herdados do padrão que funciona bem no legado (§3.2 de `00-visao-geral.md`):

- **Lado config** (`Workflow*`): o *desenho* do processo, versionado, editável só em rascunho.
- **Lado execução** (`ProcessInstance*`): uma *instância* rodando, sempre amarrada a uma
  `WorkflowVersion` específica e imutável (nunca muda de versão no meio do caminho).

**Toda entidade de negócio pertence a exatamente uma `Organization`** (empresa-cliente externa —
decisão de 2026-07-16, ver `00-visao-geral.md` §2.15) — não existe mais a noção de registro
"global"/compartilhado entre clientes que a primeira versão desta spec propunha. Detalhes de como
isso é reforçado (não só documentado) em §5.

## 2. Lado config

### 2.1 `workflows`

O processo em si (ex.: "Aprovação de Proposta Comercial"), agrupador de versões.

| coluna | tipo | notas |
|---|---|---|
| id | bigint PK | |
| organization_id | FK **not null** | todo workflow pertence a exatamente uma organização (empresa-cliente) — ver §5 |
| name | string | |
| slug | string, unique por organization | |
| description | text nullable | |
| current_published_version_id | FK nullable → workflow_versions | atalho para "a versão vigente"; novas instâncias sempre usam esta |
| created_by | FK → users | |
| timestamps | | |

### 2.2 `workflow_versions`

Uma versão congelável do desenho. **Decisão (2026-07-16, ver `00-visao-geral.md` §2.16)**: só dois
status existem — uma versão publicada **nunca muda de status de novo** (não existe "arquivada"). Qual
versão está "vigente" é só um ponteiro (`workflows.current_published_version_id`), não um estado
gravado na própria versão — isso é o que torna rollback barato (§2.2.1): reverter é só reapontar o
ponteiro para uma versão `published` mais antiga, sem tocar em status de ninguém.

| coluna | tipo | notas |
|---|---|---|
| id | bigint PK | |
| workflow_id | FK | |
| version_number | integer | incremental por workflow (1, 2, 3...), atribuído **no momento da publicação**, não da criação do draft — evita numeração com buracos por rascunhos descartados sem nunca publicar |
| status | enum: `draft`, `published` | só `draft` é editável no editor visual; uma vez `published`, imutável para sempre |
| canvas_json | json nullable | metadados de layout do canvas (viewport, agrupamentos visuais) que não pertencem à lógica do grafo — ver `03-editor-visual.md` §3 |
| published_at | timestamp nullable | preenchido uma única vez, na publicação — nunca muda depois, mesmo que a versão deixe de ser a vigente por rollback |
| created_by | FK → users | |
| timestamps | | |

**Constraint: no máximo um draft por workflow.** Decisão (2026-07-16): edição é sempre serializada —
não existem drafts concorrentes do mesmo processo. Em MySQL (sem índice único parcial/filtrado nativo
como o Postgres), a forma idiomática de aplicar isso no banco (não só na aplicação, que sozinha teria
condição de corrida) é uma coluna auxiliar nullable:

```
draft_lock_workflow_id  bigint nullable, unique
```

Preenchida com o próprio `workflow_id` quando `status = draft`, e `NULL` quando `status = published`
(MySQL trata múltiplos `NULL` como distintos num índice único, então só a linha `draft` "ocupa" a
constraint). Tentar criar um segundo draft do mesmo workflow vira uma violação de constraint única,
não uma corrida resolvida só na aplicação.

#### 2.2.1 Ciclo de vida: publicar, editar de novo, e rollback

1. **Publicar** (`draft` → `published`): atribui `version_number` (próximo da sequência do
   workflow), grava `published_at`, zera `draft_lock_workflow_id` (libera para um novo draft no
   futuro), atualiza `workflows.current_published_version_id` para esta versão, e grava uma linha em
   `workflow_version_activations` (§2.2.2) com `action = publish`.
2. **Editar de novo**: só permitido se não houver draft ativo (constraint de §2.2 garante isso). Cria
   um novo `workflow_version` (`status = draft`), **clonado da versão atualmente apontada por
   `current_published_version_id`** — não necessariamente a de maior `version_number`, já que um
   rollback pode ter deixado uma versão mais antiga como vigente; editar sempre parte do que está
   rodando de fato, não do "último publicado historicamente". Mesmo princípio de
   `Processconfig::Duplicate()` do legado, formalizado.
3. **Rollback**: escolhe qualquer outra versão `published` do mesmo workflow, atualiza
   `workflows.current_published_version_id` para apontar para ela, grava uma linha em
   `workflow_version_activations` com `action = rollback`. Não cria versão nova, não muda `status` de
   nenhuma versão (ambas continuam `published`) — só troca qual é "a vigente para novas instâncias".
   Instâncias já em andamento nunca são afetadas por publish nem por rollback, em nenhum dos dois
   casos (decisão fechada — ver `00-visao-geral.md` §2.16: instância nunca migra de versão).

#### 2.2.2 `workflow_version_activations` (log de publish/rollback)

Substitui a necessidade de um status `archived`: em vez de marcar versões antigas como "arquivadas",
registra-se cada evento de "isto passou a ser a vigente", preservando o histórico completo de quem
publicou/reverteu o quê e quando — útil tanto para auditoria quanto para a tela administrativa de
rollback (lista "voltar para a versão de tal data, publicada por fulano").

| coluna | tipo | notas |
|---|---|---|
| id | bigint PK | |
| workflow_id | FK | (denormalizado, evita join por `workflow_version` para listar histórico) |
| workflow_version_id | FK | a versão que passou a ser vigente |
| action | enum: `publish`, `rollback` | |
| activated_by | FK → users | |
| activated_at | timestamp | |

### 2.3 `workflow_steps`

Um agrupamento visual de atividades dentro de uma versão (ver risco em `00-visao-geral.md` §9 sobre
o nome/conceito — no MVP tratar como "raia"/grupo, não necessariamente sequencial).

| coluna | tipo | notas |
|---|---|---|
| id | bigint PK | |
| workflow_version_id | FK | |
| name | string | |
| sla_days | integer nullable | prazo esperado agregado do grupo, informativo |
| position_x, position_y, width, height | integer | posição/tamanho do grupo no canvas |
| sort_order | integer | ordem de exibição em listagens (não define mais o fluxo — quem define é `workflow_transitions`) |
| timestamps | | |

### 2.4 `workflow_activities`

O nó de fato do grafo.

| coluna | tipo | notas |
|---|---|---|
| id | bigint PK | |
| workflow_step_id | FK | a que grupo visual pertence |
| name | string | |
| type | enum: `task`, `automated_action`, `condition`, `form` | ver §4 |
| assignee_type | enum: `role`, `user` nullable | nullable só para `automated_action`/`condition`, que não têm responsável humano — "fila" não é um terceiro valor: é só o que acontece automaticamente quando `assignee_type = role` resolve mais de um usuário elegível (§3.4) |
| assignee_role_id | FK nullable → roles | quando `assignee_type = role` |
| assignee_user_id | FK nullable → users | quando `assignee_type = user` |
| config | json | payload específico do tipo — ver §4 |
| sla_hours | integer nullable | prazo individual da atividade (mais granular que `sla_days` do step) |
| position_x, position_y | integer | posição do nó dentro do grupo, no canvas |
| is_start | boolean default false | marca o(s) nó(s) inicial(is) da versão — precisa de exatamente ≥1 por versão publicável |
| is_end | boolean default false | marca nó(s) terminal(is) — ao concluir, `ProcessInstance.status` vira `completed` (§4.1) |
| timestamps | | |

Nota: `is_end` substitui o *magic number* `idprocessdestiny === 4` do legado (§3.3 de
`00-visao-geral.md`) por uma flag explícita e nomeada — não uma referência a uma linha de tabela
lookup.

### 2.5 `workflow_transitions`

As "arestas" do grafo — dão nome ao produto (Aresta = aresta de grafo).

| coluna | tipo | notas |
|---|---|---|
| id | bigint PK | |
| workflow_version_id | FK | (denormalizado para consultas/validação de grafo sem join extra) |
| from_activity_id | FK → workflow_activities | |
| to_activity_id | FK → workflow_activities | |
| condition_type | enum: `always`, `expression` | `always` = aresta incondicional (usada para fork paralelo, ver `02-motor-de-execucao.md` §3) |
| condition_expression | json nullable | representação estruturada da condição (builder visual, não texto livre — ver `03-editor-visual.md` §5) |
| label | string nullable | rótulo exibido na aresta (ex.: "Aprovado", "Reprovado") |
| sort_order | integer | ordem de avaliação entre transições concorrentes saindo do mesmo nó (para `expression`, a primeira que bater vence — semântica XOR) |
| timestamps | | |

Constraint de aplicação (não de banco): `from_activity_id` e `to_activity_id` devem pertencer a
`workflow_activities` da mesma `workflow_version_id` (validado no service, não via FK cross-tabela).

### 2.6 `roles` (papéis usados como responsável de atividade)

Distinto de `spatie/laravel-permission` (que cobre "quem pode editar o quê" — ver
`04-integracao-e-notificacoes.md`). Este `roles` é o equivalente ao `Profiles` do legado: um papel de
negócio usado para atribuir responsabilidade por uma atividade (ex.: "Aprovador Financeiro").

| coluna | tipo | notas |
|---|---|---|
| id | bigint PK | |
| organization_id | FK **not null** | papel de negócio é sempre de uma organização específica (ex.: "Aprovador Financeiro" do cliente X é um registro distinto do "Aprovador Financeiro" do cliente Y) |
| name | string | |
| active | boolean default true | ver §2.6.1 — desativar em vez de excluir |
| timestamps | | |

`role_user` (pivô N:N) associa usuários a papéis. Como `roles.organization_id` já amarra o papel a
uma organização, `role_user` não precisa de coluna própria de organização — mas a aplicação deve
validar, ao associar, que o usuário pertence a essa organização (via `organization_user`, §5).

#### 2.6.1 `roles` é mutável, mas nunca apagado — três decisões (2026-07-16, ver `00-visao-geral.md` §2.17)

`roles` **não é versionado** como `WorkflowVersion` (não tem draft/published/histórico) — é uma
tabela de referência simples, editável a qualquer momento. Isso é seguro mesmo com
`WorkflowVersion` sendo imutável para sempre porque `workflow_activities.assignee_role_id` guarda só
o `id` do role (não uma cópia do nome) e as três regras abaixo garantem que esse `id` continua
significando a mesma coisa ao longo do tempo:

1. **Renomear é permitido a qualquer momento, sem snapshot** — o nome exibido em qualquer tela
   (inclusive ao abrir uma `WorkflowVersion` publicada há anos, ou o histórico de uma
   `ProcessInstance` já concluída) é sempre o nome **atual** do role, resolvido via join, nunca uma
   cópia gravada no momento da execução. Renomear é só re-rotular o mesmo papel — não muda o que
   ele representa nem quebra nenhuma referência.
2. **Nunca excluir fisicamente um role referenciado** — nem por `workflow_activities` (de
   **qualquer** versão, `draft` ou `published`) nem por `process_instance_activities` (mesmo de
   instâncias já `completed`/`cancelled`, via a cadeia `process_instance_activity → workflow_activity
   → role`, estável porque a `WorkflowVersion` é imutável). Em vez disso, `active = false`
   ("desativar"/"aposentar"): remove o role dos seletores do editor para **novas** atividades, mas
   não afeta nada que já o referencia. Reforçado em dois níveis: FK com `onDelete('restrict')` como
   rede de segurança, e uma checagem na aplicação que dá um erro claro ("este papel está em uso,
   desative em vez de excluir") antes de a constraint do banco sequer ser testada.
3. **Elegibilidade (quem pertence ao role) é sempre resolvida ao vivo** — a composição de
   `role_user` no momento em que alguém tenta "assumir" uma atividade pendente é o que vale, nunca
   uma fotografia de quando a atividade foi criada. Isso vale inclusive para instâncias rodando numa
   `WorkflowVersion` antiga: um usuário que entrou no papel hoje já pode pegar uma tarefa pendente
   de uma instância iniciada há meses. `active = false` no role não invalida atribuições/filas já
   existentes (uma atividade pendente atribuída a um role desativado continua resolvível
   normalmente pelos membros atuais) — `active` só impede **novas** atividades de referenciar esse
   role no editor, e (proposta) impede adicionar **novos** membros a um role desativado via
   `role_user`, mas não remove os que já estão lá.

## 3. Lado execução

### 3.1 `process_instances`

| coluna | tipo | notas |
|---|---|---|
| id | bigint PK | |
| workflow_version_id | FK | fixado no início, nunca muda |
| organization_id | FK **not null** | denormalizado de `workflow_version.workflow.organization_id` — mantido como coluna própria (não só via join) porque é o campo mais consultado para isolamento (ver §5); toda query de instância filtra por ele diretamente |
| code | string unique | código legível (ex.: `2026-000123`), igual ao `code` do legado |
| name | string | rótulo livre da instância (ex.: nome da proposta associada) |
| status | enum: `running`, `completed`, `cancelled` | |
| started_by | FK → users | |
| started_at, completed_at | timestamp | |
| context | json | "variáveis do processo" — dados de negócio usados por condições e por integrações externas (ver `02-motor-de-execucao.md` §4) |
| timestamps | | |

### 3.2 `process_instance_steps`

Espelha `workflow_steps` no momento do início (snapshot de nome/config), para relatórios não
quebrarem se o template mudar depois — mesmo princípio de "cópia imutável" do legado (§3.2 de
`00-visao-geral.md`), só que amarrado à versão fixa em vez de precisar copiar texto manualmente
(como a versão já é imutável, isso é opcional/redundante — **decisão em aberto**: talvez esta tabela
nem precise existir, e um "agrupamento" em relatório possa ser derivado direto de
`workflow_steps` da versão fixada. Mantida aqui como proposta inicial, mas marcar como candidato a
simplificação na fase 2).

| coluna | tipo | notas |
|---|---|---|
| id | bigint PK | |
| process_instance_id | FK | |
| workflow_step_id | FK | |
| status | enum: `pending`, `active`, `completed` | `active` = tem >=1 atividade em andamento nele |
| started_at, completed_at | timestamp nullable | |

### 3.3 `process_instance_activities`

A unidade de trabalho real — equivalente a `Processactivities` + `Tasks` fundidos no legado (lá são
duas tabelas para o mesmo conceito, com uma pivô `Processacttaskfile` no meio; aqui simplificamos
para uma só).

| coluna | tipo | notas |
|---|---|---|
| id | bigint PK | |
| process_instance_id | FK | |
| workflow_activity_id | FK | de onde veio (na versão fixada) |
| assigned_user_id | FK nullable → users | resolvido no momento da criação (ver §3.4) ou depois, via fila |
| status | enum: `pending`, `in_progress`, `completed`, `skipped` | |
| form_data | json nullable | preenchido quando `workflow_activity.type = form` |
| result | json nullable | payload de saída (ex.: resultado de `automated_action`, ou qual opção foi escolhida em `condition`) — usado para avaliar `workflow_transitions.condition_expression` |
| due_at | timestamp nullable | calculado de `sla_hours` no momento da criação |
| started_at, completed_at | timestamp nullable | |
| timestamps | | |

### 3.4 Resolução de responsável (fila em vez de auto-assign frágil)

O legado (`Process::GetResponsibleUser()`) só auto-atribui quando existe exatamente 1 usuário com o
perfil — senão fica `null` até alguém "pegar" a tarefa (não há UI clara pra isso). Proposta: quando
`assignee_type = role` e há múltiplos usuários elegíveis, a atividade nasce com `assigned_user_id =
null` e aparece na "fila" (inbox) de todos os usuários daquele papel; o primeiro a clicar "assumir"
grava seu `id` (com `UPDATE ... WHERE assigned_user_id IS NULL` para evitar corrida). Detalhado em
`04-integracao-e-notificacoes.md` §3.

### 3.5 `process_instance_transition_logs`

Trilha de auditoria — **não existe equivalente formal no legado** (§3.3 de `00-visao-geral.md`: o
legado só tem `Userslogs` genérico). Essencial para a visualização de "caminho percorrido no grafo"
proposta na fase 6 de `00-visao-geral.md` §8.

| coluna | tipo | notas |
|---|---|---|
| id | bigint PK | |
| process_instance_id | FK | |
| workflow_transition_id | FK nullable | nullable pra cobrir o caso de conclusão direta (nó `is_end`, sem aresta de saída) |
| from_activity_id, to_activity_id | FK → workflow_activities | denormalizado para consulta rápida mesmo se a transição for removida numa versão futura |
| transitioned_by | FK nullable → users | nullable quando a transição foi automática (ex.: `automated_action`) |
| transitioned_at | timestamp | |

## 4. `workflow_activities.config` por tipo

O campo `config` (JSON) muda de formato conforme `type`:

- **`task`**: `{ "instructions": "texto de orientação exibido ao responsável" }`.
- **`form`**: `{ "fields": [ { "key": "valor_proposta", "label": "Valor da proposta", "type":
  "number", "required": true }, ... ] }` — schema simples de formulário dinâmico, renderizado no
  inbox de tarefas (fase 4).
- **`automated_action`**: `{ "action": "webhook" | "send_email" | "generate_document", ... payload
  específico da ação }`. Ex. para webhook: `{ "action": "webhook", "url": "...", "method": "POST" }`.
  Isso substitui o catálogo `Processactions` do legado (que era uma tabela lookup de texto livre,
  com IDs mágicos `3`/`4`/`5` referenciados no código) por um enum + payload estruturado.
- **`condition`**: não usa `config` para a lógica em si (a lógica mora nas
  `workflow_transitions.condition_expression` que saem desse nó) — `config` aqui só guarda metadados
  de exibição, ex. `{ "description": "Valor da proposta acima de R$ 50.000?" }`.

## 5. Organizações e isolamento multi-tenant

Decisão (2026-07-16, ver `00-visao-geral.md` §2.15): `Organization` = **empresa-cliente externa**
(o mesmo conceito do legado, `Organizationsprocessconfig`), e o isolamento entre organizações é uma
**fronteira de acesso real**, não um filtro de UI — um usuário de uma organização nunca deve
conseguir ver, listar ou agir sobre um `Workflow`/`ProcessInstance` de outra, mesmo por engano. Essa
garantia precisa existir desde o MVP (fases 1-3), não como algo adiado.

### 5.1 `organizations`

| coluna | tipo | notas |
|---|---|---|
| id | bigint PK | |
| name | string | |
| external_code | string nullable, unique | código para correlacionar com o mesmo cliente em outros sistemas GIITS (giits-propostas, giits-api), se eles expuserem um identificador equivalente — evita duplicar cadastro de cliente em cada sistema sem depender de um serviço central de "cliente" ainda inexistente |
| active | boolean default true | |
| timestamps | | |

### 5.2 `organization_user` (pivô N:N)

Um usuário pode pertencer a mais de uma organização — necessário para cobrir tanto usuários de uma
empresa-cliente (tipicamente 1 organização só) quanto colaboradores internos da ITS Group que dão
suporte/operam o produto através de várias organizações (ver "platform staff" em §5.3).

| coluna | tipo | notas |
|---|---|---|
| id | bigint PK | |
| organization_id | FK | |
| user_id | FK | |
| timestamps | | |

### 5.3 Como o isolamento é reforçado (não só documentado)

Três mecanismos, cada um cobrindo uma camada diferente — nenhum sozinho é suficiente:

1. **Global scope do Eloquent** em todo model com `organization_id` (`Workflow`, `WorkflowVersion` —
   via o `Workflow` pai —, `ProcessInstance`, `Role`): um `BelongsToOrganization` trait aplica um
   `Illuminate\Database\Eloquent\Scope` que filtra automaticamente por
   `whereIn('organization_id', $user->organizations->pluck('id'))` em toda query, a menos que o
   usuário autenticado tenha o papel de plataforma (abaixo). Isso protege contra o erro mais comum
   (esquecer um `where` num controller novo) por padrão, em vez de depender de disciplina manual.
2. **Policies** (`WorkflowPolicy`, `ProcessInstancePolicy`, `RolePolicy`) para as ações que não são
   simples listagem (`view`, `update`, `delete`, publicar) — checam explicitamente
   `$user->organizations->contains($model->organization_id)` **e** a permissão RBAC adequada (ver
   `04-integracao-e-notificacoes.md` §2.1). O global scope cobre "não aparece na lista"; a policy
   cobre "não é possível acessar diretamente pela URL/ID mesmo sabendo que existe".
3. **`spatie/laravel-permission` com o recurso de *teams*** (`organization_id` mapeado como o
   `team_id` nativo do pacote) para a camada de RBAC administrativo (`workflow-admin`/
   `workflow-editor`/`workflow-viewer`, `04-integracao-e-notificacoes.md` §2.1) — evita reimplementar
   escopamento por organização à mão nessa camada, já que o pacote já resolve isso nativamente.

**Papel de plataforma** (`platform-staff`, ITS Group): usuários com esse papel (não amarrado a
`organization_user` — ou amarrado a todas, dependendo da implementação do bypass) enxergam através
do global scope, para suporte/operação — toda ação desse tipo é registrada como administrativa nos
logs de auditoria (`process_instance_transition_logs.transitioned_by` continua sendo o usuário real,
não um usuário "sistema", para rastreabilidade).

### 5.4 Sistemas externos (API) não são organizações

Um token Sanctum (`04-integracao-e-notificacoes.md` §5) identifica **qual sistema** está chamando
(giits-propostas, giits-api), não uma organização — o mesmo sistema externo tipicamente atende
várias organizações (do mesmo jeito que o giits-propostas atende vários clientes). Cada chamada de
API que afeta um recurso organizacional deve informar explicitamente a `organization_id` alvo no
payload, e o backend valida que (a) essa organização existe/está ativa e (b) o token do sistema
chamador tem permissão de integração geral (não há, no MVP, uma lista fina de "sistema X só pode
atuar nas organizações 1, 2, 3" — todo sistema GIITS interno autenticado via Sanctum é considerado
confiável para atuar em qualquer organização ativa, já que a fronteira de confiança real é "é um
sistema GIITS interno ou não", não entre sistemas GIITS entre si). Ver item em aberto em §7.

## 6. Migrations — ordem sugerida

1. `organizations`, `organization_user`.
2. `roles`, `role_user` (dependem de `organizations`).
3. `workflows`, `workflow_versions` (já com `draft_lock_workflow_id`, §2.2).
4. `workflow_version_activations` (depende de `workflow_versions`).
5. `workflow_steps`, `workflow_activities` (com FK para `workflow_steps`).
6. `workflow_transitions` (FK para `workflow_activities`, ambas direções).
7. Alter `workflows` para adicionar `current_published_version_id` (FK circular com
   `workflow_versions` — precisa ser adicionada depois que a tabela existir).
8. `process_instances`, `process_instance_steps`, `process_instance_activities`,
   `process_instance_transition_logs`.

## 7. Itens em aberto

- Confirmar se `process_instance_steps` deve mesmo existir (ver §3.2) ou se é simplificação
  prematura — decidir na fase 2, com base em como os relatórios de progresso vão consultar dados.
- Definir o schema exato de `condition_expression` (builder visual) junto com
  `03-editor-visual.md` §5 antes de implementar a fase 2 do motor, já que o motor precisa saber
  avaliar esse JSON.
- Se `context` de `process_instances` crescer muito (processos com muitos campos de formulário ao
  longo do caminho), avaliar mover para uma tabela `process_instance_variables` chave/valor em vez
  de um único JSON — não fazer isso preventivamente, só se o JSON se mostrar limitante na prática.
- **Sem template global/compartilhado entre clientes**: como `workflows.organization_id` agora é
  obrigatório (§2.1), não existe mais um "processo padrão da ITS Group" visível a todas as
  organizações — se dois clientes têm o mesmo processo de negócio, hoje a única forma de reaproveitar
  é um `platform-staff` duplicar (clonar) o desenho de uma organização para outra manualmente,
  criando um segundo `Workflow` independente (mesma mecânica de `Processconfig::Duplicate()` do
  legado, §3.4 de `00-visao-geral.md`, só que cruzando organização em vez de só criar uma cópia na
  mesma). Essa é uma simplificação deliberada para não construir uma segunda categoria "workflow
  compartilhado" com regras de autorização próprias antes de saber se isso é realmente necessário —
  mas é uma escolha real de produto (menos DRY entre clientes, mais simples de raciocinar sobre
  isolamento), não um detalhe técnico. Se no futuro fizer falta, é uma migration aditiva (permitir
  `organization_id` nulo de novo) e uma regra de autorização a mais, não uma reestruturação. Nota de
  implementação: essa clonagem entre organizações e a clonagem "editar de novo" dentro do mesmo
  workflow (§2.2.1, item 2) são operações irmãs — ambas partem de um `WorkflowVersion` existente e
  copiam step/activity/transition para um novo `WorkflowVersion` `draft`; só muda o `workflow_id`
  (e portanto `organization_id`) de destino. Vale implementar como um único serviço de clonagem
  parametrizado pelo workflow/organização alvo, não duas rotinas separadas.
- **Escopo de token Sanctum por sistema × organização** (§5.4): o MVP trata "é um sistema GIITS
  autenticado" como suficiente para atuar em qualquer organização ativa. Se, na prática, um sistema
  externo não deveria poder ver/iniciar processos de organizações que ele mesmo não atende, isso
  precisa de uma tabela de mapeamento sistema↔organizações permitidas — não implementada nesta
  versão da spec, revisitar quando o primeiro consumidor real (fase 5) tiver esse requisito
  concreto.
