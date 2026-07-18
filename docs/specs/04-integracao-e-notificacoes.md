# Spec: permissões, notificações e integração externa

Status: proposta, ainda não implementada. Depende de `00-visao-geral.md`, `01-modelo-de-dados.md` e
`02-motor-de-execucao.md`.

## 1. Autenticação

SSO Microsoft (Azure AD/Entra ID) via `Laravel Socialite` + `socialiteproviders/microsoft`, mesmo
padrão do GIITS Status (`~/Documents/Projects/aresta.dev`, ver `docs/deploy-hostinger.md` e
`README.md` daquele projeto para configuração de App Registration/redirect URI). Sem tela de login
própria do Filament — login sempre pela tela principal da aplicação, `/admin` reaproveita a mesma
sessão.

## 2. Permissões (três camadas — detalhando `00-visao-geral.md` §6)

### 2.0 Camada 0 — isolamento entre organizações (não é RBAC, é uma fronteira anterior)

Antes de qualquer permissão, um usuário só enxerga organizações a que pertence
(`organization_user`, `01-modelo-de-dados.md` §5.2), via global scope aplicado a todo model
organizacional. Isso é o que garante que um usuário da organização A nunca vê sequer a *existência*
de um `Workflow`/`ProcessInstance` da organização B — as camadas abaixo (2.1/2.2) só decidem o que
ele pode fazer *dentro* das organizações que já enxerga. Mecanismo completo em
`01-modelo-de-dados.md` §5.3.

### 2.1 Camada "quem administra o produto/templates" — `spatie/laravel-permission`

RBAC clássico, usando o recurso nativo de ***teams*** do pacote com `organization_id` como o
`team_id` — evita reimplementar escopamento por organização nesta camada, já que o pacote já separa
automaticamente as permissões de um usuário por *team*/organização:

- `workflow-admin`: cria/edita/publica/arquiva `Workflow`/`WorkflowVersion` da própria organização;
  gerencia `roles` (papéis de negócio) e usuários da organização.
- `workflow-editor`: edita rascunhos (`draft`), mas não publica nem arquiva.
- `workflow-viewer`: só visualiza processos e instâncias (relatórios, acompanhamento), sem editar.
- `platform-staff` (ITS Group): não é um papel *dentro* de uma organização, é o override de §2.0 —
  atua através do isolamento de qualquer organização para fins de suporte/operação, com toda ação
  registrada nos logs de auditoria como a do usuário real (`01-modelo-de-dados.md` §5.3).

Aplicado via `Gate`/policies do Laravel nas rotas de `WorkflowController`/`WorkflowVersionController`
e no editor visual (Inertia só renderiza o canvas em modo edição se o usuário tiver
`workflow-editor` ou superior na organização dona do processo).

### 2.2 Camada "quem executa atividades" — atribuição no próprio desenho do processo

**Não é RBAC genérico** — é resolvido por `workflow_activities.assignee_type/assignee_role_id/
assignee_user_id` (`01-modelo-de-dados.md` §2.4), avaliado em tempo de execução pelo
`WorkflowEngine`, sempre dentro da organização dona da instância (§2.0 já garante que a atividade e
o usuário elegível são da mesma organização — `role.organization_id`, `01-modelo-de-dados.md` §2.6).
Um usuário só pode completar uma `process_instance_activity` se:

- `assigned_user_id` já é ele, **ou**
- `assigned_user_id` é nulo e ele pertence ao `role` da atividade (pode "assumir" — ver §3), **ou**
- ele tem `workflow-admin` **daquela organização**, ou é `platform-staff` (override administrativo,
  para casos de responsável ausente/afastado — registrado no log de auditoria como ação
  administrativa, não como conclusão normal).

Isso preserva o que já funcionava bem no legado (perfil responsável por atividade, `Profiles`) sem
o auto-assign frágil (só funciona com exatamente 1 usuário elegível) — ver §3.

"Pertence ao `role` da atividade" é sempre resolvido pela composição **atual** de `role_user`, nunca
uma fotografia de quando a atividade nasceu — decisão fechada em `01-modelo-de-dados.md` §2.6.1
(regra 3), vale inclusive para atividades de instâncias rodando numa `WorkflowVersion` antiga: um
usuário que entrou no papel depois da instância ter começado já pode assumir a tarefa pendente. Um
`role` desativado (`active = false`) não invalida atividades que já o referenciam nem impede
resolver quem pode assumi-las — só bloqueia o role de ser escolhido para atividades **novas**
(`03-editor-visual.md` §6).

## 3. Fila de atividades ("inbox") e resolução de responsável

- Tela "Minhas tarefas": lista `process_instance_activities` com `status in (pending, in_progress)`
  e (`assigned_user_id = auth()->id()` OU (`assigned_user_id IS NULL` E usuário pertence ao `role`
  da atividade, independente do role estar `active`)).
- Ação "Assumir" nas atividades ainda sem responsável: `UPDATE process_instance_activities SET
  assigned_user_id = ? WHERE id = ? AND assigned_user_id IS NULL` (condição no `WHERE` evita corrida
  entre dois usuários clicando "assumir" ao mesmo tempo — só um `UPDATE` afeta linha).
- Quando `assignee_type = user` (responsável fixo definido no desenho, não por papel), a atividade
  já nasce atribuída, sem passar pela fila.

## 4. Notificações (Laravel Notifications — canais `database` + `mail`)

Eventos que disparam notificação:

1. **Atividade atribuída**: ao criar `process_instance_activity` com `assigned_user_id` já resolvido
   (responsável fixo), ou a todos os usuários elegíveis quando cai na fila (`assigned_user_id null`,
   por papel).
2. **Atividade assumida por outro alguém da fila**: notifica os demais elegíveis que a tarefa não
   está mais disponível (evita "trabalho duplicado" — melhoria em relação ao legado, que não tinha
   noção de fila/concorrência).
3. **SLA vencendo** (ex.: 80% do prazo consumido) e **SLA vencido** (`due_at < now()`) — job agendado
   descrito em `02-motor-de-execucao.md` §5.
4. **Processo concluído**: notifica quem iniciou a instância (`started_by`).
5. **Ação automática falhou** (ex.: webhook retornou erro): notifica `workflow-admin` da organização
   — o legado não tinha esse conceito (ações condicionais estavam todas desabilitadas), então este é
   um cuidado novo: uma `automated_action` que falha não deve travar a instância silenciosamente;
   fica em `status = in_progress` com um flag de erro visível na tela de acompanhamento, e notifica
   o admin para intervenção manual.

## 5. API pública (Laravel Sanctum)

Para outros sistemas GIITS (giits-propostas, giits-api) iniciarem e acompanharem processos
programaticamente. Tokens Sanctum por aplicação cliente (não por usuário final).

Endpoints propostos (fase 5, `00-visao-geral.md` §8):

- `POST /api/workflows/{slug}/instances` — inicia uma instância (`context` inicial no body). Retorna
  `code`/`id` da instância criada.
- `GET /api/instances/{code}` — status atual (etapa(s)/atividade(s) ativas, `context`, histórico
  resumido).
- `GET /api/instances/{code}/activities` — lista atividades da instância (útil para o sistema
  externo espelhar/exibir progresso sem replicar o motor).
- `POST /api/instances/{code}/activities/{id}/complete` — completa uma atividade programaticamente
  (equivalente à ação automática, mas disparada de fora) — só permitido se a `workflow_activity`
  correspondente permitir conclusão via API (flag futura em `config`, não coberta no MVP: no MVP,
  restringir esse endpoint a atividades `type = automated_action` cujo "automação" é justamente "o
  sistema externo confirma via API" em vez de webhook de saída).
- Webhooks de saída (o Aresta Workflow Manager chamando o sistema externo, não o contrário) já estão
  cobertos por `type = automated_action` (`01-modelo-de-dados.md` §4) — não precisam de endpoint
  novo, são configuração de nó.

Todo endpoint que recebe/retorna um recurso organizacional exige `organization_id` explícito no
payload (ex.: `POST /api/workflows/{slug}/instances` recebe a organização alvo, já que um mesmo
sistema externo atende várias organizações — não é o token que define a organização, é o request).
O backend valida que essa organização existe/está ativa; no MVP, qualquer sistema GIITS autenticado
via Sanctum é considerado confiável para atuar em qualquer organização ativa (sem lista fina de
"sistema X só pode atuar nas organizações Y, Z") — mecanismo completo e a ressalva sobre esse
MVP em `01-modelo-de-dados.md` §5.4.

## 6. Itens em aberto

- Rate limiting e retry policy para `automated_action` do tipo webhook (quantas tentativas, backoff)
  — não detalhado nesta versão da spec; propor usar o sistema de filas/retry padrão do Laravel
  (`ShouldQueue` + `tries`/`backoff` no job) quando chegar a fase 5.
- Escopo exato dos tokens Sanctum por sistema externo (1 token = 1 organização, ou um token pode
  operar em múltiplas?) — mesmo item já registrado em `01-modelo-de-dados.md` §7 e
  `00-visao-geral.md` §9; decidir quando o primeiro consumidor real (provavelmente giits-propostas)
  tiver requisitos concretos.
