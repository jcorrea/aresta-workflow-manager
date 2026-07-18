# Spec: Aresta Workflow Manager — visão geral e arquitetura

Status: proposta, ainda não implementada (projeto ainda não scaffoldado).

## 1. Objetivo

Construir um produto novo e independente — o **Aresta Workflow Manager** — para modelar e executar
processos de negócio (workflows) compostos por **etapas** e **atividades**, com um **editor visual
de arrastar-e-conectar** (canvas gráfico) para desenhar o fluxo, no lugar do formulário tabular
usado hoje no sistema legado `giits-propostas`.

Não é um projeto para substituir o `giits-propostas` nem para migrar dados dele. É um produto novo,
inspirado no *conceito* do módulo de processos daquele sistema (processo → etapas → atividades,
template configurável → instância em execução), mas com stack, modelo de dados e motor de execução
próprios — pensado desde já para ser reutilizável por outros sistemas do grupo (giits-propostas,
giits-api, etc.) via API, e para suportar fluxos não-lineares (grafo/DAG), o que o legado nunca
suportou de fato.

Decisões confirmadas em entrevista com o usuário (2026-07-16) — ver §2. Decisões técnicas derivadas
da avaliação do legado — ver §3 e o relatório completo arquivado no histórico da conversa que
originou esta spec.

## 2. Decisões de produto (entrevista, 2026-07-16)

1. **Produto novo e independente** — não é um "reskin" do giits-propostas; pode (e deve) evoluir
   para atender outros sistemas GIITS no futuro via API.
2. **Modelagem + execução completa** — não é só uma ferramenta de desenho. O sistema precisa abrir
   instâncias de processo reais, mover entre etapas, atribuir responsáveis, controlar prazos/SLA —
   equivalente em capacidade ao motor do legado, só que desenhado corretamente desde o início.
3. **Multi-processo genérico** — qualquer área pode cadastrar seu próprio processo de negócio
   (proposta, compras, RH, etc.), cada um com seu fluxo próprio. Não é um sistema de domínio único.
4. **Usuário-alvo do editor visual**: administradores/analistas de negócio, **não** desenvolvedores —
   a UI do canvas precisa ser amigável para quem não programa (nomes de campo claros, validação
   visual de fluxo incompleto, sem exigir conhecimento de BPMN formal).
5. **Sem referência de ferramenta fixada** — nem estilo n8n puro, nem BPMN formal com raias, nem
   Kanban linear. Ver §4 para a proposta de abordagem (um meio-termo: grafo livre, mas com
   agrupamento visual por etapa).
6. **Atividades podem ser**: tarefa manual de um usuário/papel, ação automática/integração
   (webhook, e-mail, gerar documento), condição/decisão (bifurca o fluxo), ou formulário/coleta de
   dados. Um único tipo de nó "atividade" com um campo `type` cobre os quatro casos — ver
   `docs/specs/01-modelo-de-dados.md` §4.
7. **Topologia do fluxo**: **grafo completo (DAG)** — uma atividade pode ter mais de uma saída
   condicional, caminhos paralelos e convergência (join). Isso é o principal salto de capacidade em
   relação ao legado, cuja "máquina de estados" é linear e implícita (ver §3.3).
8. **Versionamento formal**: cada processo tem versões (`draft` → `published` → nova versão),
   instâncias em andamento continuam amarradas à versão vigente no momento em que foram iniciadas.
   O editor só edita rascunhos; publicar congela uma versão. Isso resolve o maior ponto fraco do
   legado (só duplicação manual, sem histórico real — ver §3.4).
9. **Notificações**: in-app e por e-mail (atividade atribuída, prazo vencendo/vencido, processo
   concluído).
10. **API para sistemas externos**: outros sistemas GIITS devem poder iniciar processos, consultar
    status e (eventualmente) avançar etapas programaticamente.
11. **Permissões**: em aberto, a cargo desta spec propor — ver §6.
12. **Contexto**: já é uma iniciativa da ITS Group, não um protótipo pessoal — vale investir em
    qualidade de spec e em uma base arquitetural sólida desde o início.
13. **Convenções de infraestrutura**: seguir o mesmo padrão do outro projeto novo da suíte Aresta
    (`aresta.dev`, internamente "GIITS Status") — Podman para dev sem exigir PHP no host, Filament
    para back-office administrativo, SSO Microsoft para login. Ver §7.
14. **Join sempre AND, garantido por validação estrutural no editor** (decisão de acompanhamento,
    2026-07-16): não existe OR-join no produto. Todo ponto de convergência do grafo espera todos os
    caminhos paralelos, e o editor visual proíbe, no desenho, qualquer bloco fork/join mal-formado
    que pudesse travar uma instância em produção esperando uma chegada que nunca acontece. Detalhado
    em `02-motor-de-execucao.md` §3.3 (mecânica e motivação) e `03-editor-visual.md` §6 (regra de
    validação de publicação).
15. **`Organization` = empresa-cliente externa, com isolamento real** (decisão de acompanhamento,
    2026-07-16): "organização" não é uma área interna da ITS Group nem "qual sistema chamou a API" —
    é o mesmo conceito do legado (`Organizationsprocessconfig`), uma empresa-cliente. O isolamento
    entre organizações é uma **fronteira de acesso de verdade** (RBAC/policy, não um filtro de UI
    opcional), e precisa estar pronto **já no MVP** (fases 1-3), não adiado para quando houver um
    caso concreto. Isso é mais rígido do que a proposta original desta spec (que tinha
    `organization_id` nullable/"global" em várias tabelas) — corrigido em
    `01-modelo-de-dados.md` §5, com o mecanismo de reforço (global scopes + policies + *teams* do
    `spatie/laravel-permission`) detalhado ali e em `04-integracao-e-notificacoes.md` §2.
16. **Mecânica de versionamento refinada** (decisão de acompanhamento, 2026-07-16, detalhando o item
    8): três escolhas fechadas que o item 8 original deixava implícitas —
    - **Um draft ativo por vez, por processo** — nunca dois rascunhos concorrentes do mesmo
      `Workflow`. Para explorar uma variante, duplica-se para um `Workflow` separado (mesmo mecanismo
      de clonagem entre organizações, `01-modelo-de-dados.md` §7).
    - **Instância nunca migra de versão** — presa para sempre à `WorkflowVersion` em que começou,
      mesmo que uma versão mais nova seja publicada ou um rollback aconteça depois. Corrigir o
      desenho só afeta instâncias futuras.
    - **Rollback suportado**: reativar uma versão antiga já publicada como a vigente de novo é uma
      operação de primeira classe (não precisa recriar um draft do zero) — trocou o enum de status de
      `draft/published/archived` para só `draft/published` (uma versão publicada nunca muda de status
      de novo; "vigente" é só um ponteiro, `workflows.current_published_version_id`), com um log de
      ativações (`workflow_version_activations`) registrando cada publish/rollback para auditoria.
    Detalhado em `01-modelo-de-dados.md` §2.2/§2.2.1/§2.2.2.
17. **`roles` (papéis de negócio) não são versionados como `WorkflowVersion`, mas nunca são apagados**
    (decisão de acompanhamento, 2026-07-16): diferente do processo em si, um `role` (ex.: "Aprovador
    Financeiro") é uma tabela de referência simples, editável a qualquer momento — mas isso só é
    seguro porque três regras garantem que o `id` de um role continua significando a mesma coisa ao
    longo do tempo, mesmo dentro de uma `WorkflowVersion` publicada (imutável para sempre, item 16):
    renomear é livre e sem snapshot (nome exibido é sempre o atual, inclusive em histórico antigo);
    excluir fisicamente um role em uso (por qualquer versão ou instância, mesmo histórica) nunca é
    permitido — só desativar (`roles.active = false`); e a elegibilidade de quem pode assumir uma
    atividade é sempre resolvida pela composição **atual** do role, nunca uma fotografia do passado,
    mesmo para instâncias rodando numa versão antiga do processo. Detalhado em
    `01-modelo-de-dados.md` §2.6.1.

## 3. O que aproveitar do legado (giits-propostas) e o que corrigir

Resumo da avaliação completa do módulo de processos do `giits-propostas` (repositório em
`~/Documents/Projects/giits-propostas`):

### 3.1 Achado crítico: o legado não é Laravel

O `giits-propostas` usa um framework PHP proprietário (roteador e ORM caseiros, motor de template
próprio, PostgreSQL via PDO puro) — **não** Eloquent, Blade, Livewire, Vue ou Inertia. Isso significa
que nada de código é reaproveitável; o que vale a pena preservar é o **modelo conceitual** (config
vs. instância) e as **lições sobre o que não funcionou** (state machine linear, sem versionamento,
sem editor visual).

### 3.2 Padrão que vale preservar: "Config vs. Instância"

O legado separa `Processconfig`/`Processconfigsteps`/`Processconfigactivities` (o **template**,
editável) de `Process`/`Processsteps`/`Processactivities` (a **instância**, um snapshot imutável
tirado do template no momento em que o processo é iniciado). Esse padrão é sólido e é a base do
modelo de dados desta spec (`docs/specs/01-modelo-de-dados.md`), só que com versionamento formal em
vez de cópia manual.

### 3.3 O que corrigir: state machine linear e implícita

No legado, o avanço de etapa é dirigido por ordenação numérica (`activitysort`) e um campo
`idprocessdestiny` cujo valor `4` significa "arquivar processo" — um *magic number* amarrado a uma
linha de tabela lookup, não a um enum. A lógica de condições por tipo de ação
(`Tasks::WorkflowType()`) está **comentada/morta** no código atual — na prática, o motor hoje só
"sempre avança para a próxima atividade" em sequência. Não há paralelismo, não há bifurcação
condicional de verdade, não há trilha de auditoria de transições.

Esta spec propõe (§`02-motor-de-execucao.md`) uma state machine explícita, com transições
(`WorkflowTransition`) como entidades de primeira classe do grafo — não como um campo de lookup —,
suportando fork/join (AND) e decisão condicional (XOR), com log de auditoria de cada transição.

### 3.4 O que corrigir: sem versionamento real

`Processconfig::Duplicate()` é a única forma de "reaproveitar" um fluxo — uma cópia manual, sem
histórico, sem `draft`/`published`, sem proteção contra editar um template enquanto processos rodam
nele (no legado, isso é resolvido só porque a instância copia o texto no início — mas editar demais
o template não gera uma nova versão rastreável). Esta spec propõe versionamento formal (item 8,
detalhado — junto com a mecânica de rollback e a regra de um draft por vez — no item 16 e em
`01-modelo-de-dados.md` §2.2).

### 3.5 O que corrigir: sem editor visual

Toda a configuração de processo no legado é um formulário tabular HTML com **20 etapas e 5
atividades por etapa pré-alocadas em branco** (`Processconfig::GetSteps()`/`GetActivities()`) — sem
canvas, sem drag-and-drop, sem visualização gráfica nem para configurar nem para acompanhar o
andamento (o progresso é uma barra horizontal por etapa, não um grafo). Esse é o gap central que
motiva este produto — ver `docs/specs/03-editor-visual.md`.

## 4. Arquitetura de alto nível

```
┌─────────────────────────────────────────────────────────────┐
│  Editor visual (Vue 3 + Inertia + Vue Flow)                  │
│  - desenha WorkflowStep (grupo/"raia") e WorkflowActivity     │
│    (nó) dentro do grupo, conecta via WorkflowTransition        │
│  - só opera sobre WorkflowVersion em draft                    │
└───────────────────────┬───────────────────────────────────────┘
                         │ Inertia (props/visits)
┌───────────────────────▼───────────────────────────────────────┐
│  Laravel 13 app                                                │
│  ┌───────────────┐  ┌────────────────────┐  ┌───────────────┐│
│  │ Workflow       │  │ Engine de execução  │  │ API pública   ││
│  │ (config/CRUD,  │  │ (WorkflowEngine     │  │ (Sanctum,     ││
│  │ versionamento) │  │  service, avança    │  │  start/status/││
│  │                │  │  instâncias)        │  │  advance)     ││
│  └───────┬────────┘  └──────────┬──────────┘  └───────┬───────┘│
│          │                      │                      │       │
│  ┌───────▼──────────────────────▼──────────────────────▼─────┐│
│  │ Banco (MySQL/Postgres) — ver 01-modelo-de-dados.md          ││
│  └──────────────────────────────────────────────────────────┘│
│  Filament (/admin): CRUD de Role/Organization/lookup tables    │
│  Notificações: Laravel Notifications (database + mail)         │
│  SSO Microsoft (Socialite), igual ao GIITS Status               │
└─────────────────────────────────────────────────────────────────┘
```

Ver specs específicas:
- `01-modelo-de-dados.md` — entidades, migrations, versionamento.
- `02-motor-de-execucao.md` — state machine, avaliação de transições, fork/join, SLA.
- `03-editor-visual.md` — Vue Flow, tipos de nó, persistência do canvas, UX para usuário de negócio.
- `04-integracao-e-notificacoes.md` — API, permissões, notificações, SSO.

## 5. Stack técnica

- **Laravel 13 / PHP 8.3+** — mesma versão do GIITS Status, para manter consistência na suíte Aresta.
- **Vue 3 + Inertia** — decisão explícita do usuário para o editor visual (diferente do GIITS Status,
  que é Blade + JS vanilla; aqui se justifica pela riqueza de interação do canvas de arrastar-e-
  conectar, que um framework reativo facilita muito mais que manipulação manual de DOM).
- **Vue Flow** (`@vue-flow/core`) — biblioteca de canvas de nós/arestas para Vue 3, equivalente ao
  React Flow. Madura, suporta nós customizados, minimapa, controles de zoom/pan, validação de
  conexão, e edição de posição (drag). É a peça central do editor visual — ver
  `03-editor-visual.md` §2 para justificativa e alternativas descartadas.
- **MySQL** (via Docker Compose, como o GIITS Status oferece) para dev/produção; **SQLite** em
  memória para testes automatizados, mesmo padrão do GIITS Status.
- **Filament 5** — back-office administrativo (`/admin`) para entidades de apoio (Organization,
  Role/perfil, catálogo de ações automáticas), não para o editor visual em si (Filament não é
  adequado para canvas livre — ver `03-editor-visual.md` §1).
- **Laravel Socialite + `socialiteproviders/microsoft`** — SSO Azure AD/Entra ID, igual ao GIITS
  Status.
- **Laravel Sanctum** — autenticação de API para os sistemas externos (giits-propostas, giits-api)
  consumirem o motor de workflow.
- **spatie/laravel-permission** — RBAC (papéis/permissões), granular o suficiente para cobrir tanto
  "quem pode editar templates" quanto "quem pode executar que tipo de atividade" — ver
  `04-integracao-e-notificacoes.md` §2.
- Ambiente de dev via **Podman** sem exigir PHP no host (scripts `bin/composer`, `bin/artisan`,
  `bin/php`, `bin/serve`), igual ao GIITS Status — ver §7.

## 6. Permissões e isolamento multi-tenant

Três camadas distintas, que não devem ser confundidas (decisão de isolamento real firmada em
2026-07-16, §2.15 — esta seção foi reescrita para refletir isso; a versão original tratava
organização como opcional):

1. **Isolamento entre organizações (empresas-clientes)** — a camada mais fundamental: um usuário só
   enxerga/acessa dados da(s) organização(ões) a que pertence, ponto. Não é RBAC ("o que posso fazer
   dentro da organização"), é uma fronteira anterior a qualquer permissão ("quais organizações eu
   nem sei que existem"). Implementada via global scope + policies + *teams* do
   `spatie/laravel-permission` — detalhado em `01-modelo-de-dados.md` §5.
2. **Quem pode editar templates de processo dentro da organização** (`WorkflowVersion` em draft):
   RBAC clássico via `spatie/laravel-permission` — papéis tipo `workflow-admin`, `workflow-editor`,
   `workflow-viewer`, escopados por organização usando o recurso de *teams* do pacote (que já mapeia
   1:1 para `organization_id`, evitando reimplementar esse escopo à mão nesta camada).
3. **Quem pode executar uma atividade de uma instância em andamento**: resolvido pelo
   `assignee_type`/`assignee_role_id` da própria `WorkflowActivity` (ver `01-modelo-de-dados.md`
   §2.4) — não é RBAC genérico, é uma atribuição definida no desenho do processo (papel responsável,
   usuário específico, ou fila/round-robin), sempre dentro dos limites de uma única organização (um
   `Role` de negócio já pertence a uma organização — `01-modelo-de-dados.md` §2.6). Isso é o que o
   legado já fazia bem (perfil responsável por atividade) e deve ser preservado, só que sem o
   auto-assign frágil de "se só existe 1 usuário com esse perfil" — propor fila explícita com "pegar
   para mim" quando há mais de um elegível.

Detalhamento completo em `04-integracao-e-notificacoes.md` §2, e do mecanismo de isolamento em
`01-modelo-de-dados.md` §5.

## 7. Convenções de projeto (herdadas do GIITS Status / suíte Aresta)

- Scripts `bin/composer`, `bin/artisan`, `bin/php`, `bin/serve` rodando dentro de um container
  Podman (mesmo `Containerfile` de base do GIITS Status, com `pdo_mysql` no lugar de/além de
  `pdo_sqlite`).
- `docker-compose.yml` para MySQL local + (opcional) SonarQube apontando para a VM compartilhada já
  usada pelos outros projetos.
- Testes automatizados em SQLite em memória (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), mesmo
  que produção use MySQL.
- Filament em `/admin`, acessível só após login via SSO Microsoft na tela principal (mesma regra do
  GIITS Status: sem tela de login própria do Filament).
- Projeto novo, sem base de usuários em produção ainda — pode refatorar/reestruturar livremente
  durante o desenvolvimento inicial, mas com o mesmo cuidado de testar os fluxos principais antes de
  considerar uma mudança pronta (login → criar processo → desenhar fluxo → publicar → iniciar
  instância → executar atividades → concluir).

## 8. Plano de fases sugerido

Cada fase é entregável isoladamente e validável antes de avançar para a próxima — o motor de
execução (fases 1-2) deve estar correto e testado *antes* de investir no editor visual (fase 3),
já que o canvas é "só" uma interface sobre um grafo que precisa estar bem modelado por baixo.

1. **Fase 0 — Scaffold**: Laravel 13 novo, Podman/Docker Compose, Filament, SSO Microsoft, CI básico
   (testes + Sonar), estrutura de organizações/usuários/papéis **com isolamento multi-tenant real
   desde o início** (global scopes + policies + *teams* do `spatie/laravel-permission`, ver
   `01-modelo-de-dados.md` §5 e `00-visao-geral.md` §6) — não é um item a reboque de fase futura,
   dado que a decisão (§2.15) exige enforcement já no MVP.
2. **Fase 1 — Modelo de dados "config"**: migrations de `Workflow`/`WorkflowVersion`/`WorkflowStep`/
   `WorkflowActivity`/`WorkflowTransition`/`WorkflowVersionActivation` (ver `01-modelo-de-dados.md`),
   CRUD básico via Filament ou Inertia simples (sem canvas ainda) só para validar o modelo com dados
   reais — inclui publicar/rollback (§2.2.1), já que essa mecânica não depende do canvas e vale
   validar cedo.
3. **Fase 2 — Motor de execução**: `WorkflowEngine` service, entidades de instância
   (`ProcessInstance`/`ProcessInstanceStep`/`ProcessInstanceActivity`), avaliação de transições
   (fork/join/condição), log de auditoria. Testado por testes automatizados (unitários + feature),
   sem UI rica ainda — só uma lista simples de tarefas pendentes.
4. **Fase 3 — Editor visual**: Vue 3 + Inertia + Vue Flow, CRUD visual de step/activity/transition
   sobre uma `WorkflowVersion` em draft, `WorkflowGraphValidator` (grafo incompleto, fork/join
   mal-formado — ver `02-motor-de-execucao.md` §3.3.1), fluxo de publicar versão.
5. **Fase 4 — Inbox de tarefas + notificações**: tela do usuário final para ver/executar atividades
   atribuídas a ele (incluindo preenchimento de formulário quando `type=form`), notificações in-app
   e e-mail.
6. **Fase 5 — API externa**: endpoints Sanctum para outros sistemas GIITS iniciarem processos,
   consultarem status e avançarem atividades programaticamente.
7. **Fase 6 — Observabilidade do fluxo**: visualização do caminho real percorrido por uma instância
   sobre o grafo do processo (diferencial forte em relação ao legado, que só mostra barra de
   progresso linear) — reaproveita o mesmo componente Vue Flow do editor, em modo somente-leitura,
   colorindo os nós/arestas percorridos.

## 9. Riscos / itens em aberto

- **Custo de implementação do `WorkflowGraphValidator`**: a validação de fork/join bem-formado
  (§2.14, `02-motor-de-execucao.md` §3.3.1) usa análise de pós-dominância sobre o grafo — técnica
  bem documentada, mas não trivial de implementar corretamente nem de testar (precisa de casos de
  blocos aninhados, sobrepostos, e combinados com ciclos). Reservar tempo de fase 3 real para isso,
  não tratar como um detalhe menor do editor visual.
- **Linguagem de condição**: para `type=condição/decisão`, precisa decidir se as expressões são
  construídas via UI (builder visual tipo "se campo X > valor Y") ou uma linguagem de expressão
  textual (ex.: `symfony/expression-language`). Dado que o usuário-alvo é administrador de negócio
  (não dev), a proposta é builder visual — ver `03-editor-visual.md` §5.
- **Multi-tenancy — resolvido em 2026-07-16** (§2.15, `01-modelo-de-dados.md` §5): `Organization` =
  empresa-cliente externa, isolamento é fronteira de acesso real, obrigatório desde o MVP. Itens
  derivados dessa decisão que ainda ficam em aberto:
  - **Trocador de organização na UI**: como `organization_user` permite um usuário pertencer a mais
    de uma organização (ex.: `platform-staff` da ITS Group), a tela de listagem de workflows precisa
    de algum seletor de "organização atual" (padrão tipo troca de workspace) — não especificado
    nesta versão da spec, resolver junto com as telas da fase 1.
  - **Sem template compartilhado entre clientes**: `workflows.organization_id` obrigatório elimina a
    ideia original de "processo global da ITS Group" — reaproveitar um desenho entre clientes vira
    responsabilidade manual (duplicar/clonar), não um recurso de primeira classe. Ver
    `01-modelo-de-dados.md` §7 para o raciocínio completo — é uma escolha deliberada de simplicidade,
    não um esquecimento.
- **Versionamento de template — mecânica resolvida em 2026-07-16** (item 16, `01-modelo-de-dados.md`
  §2.2): um draft por vez, instância nunca migra de versão, rollback suportado via ponteiro +
  log de ativações. Único item que sobra em aberto: a UI de rollback (fase 3) é simples de
  especificar em cima do que já existe (`workflow_version_activations` já dá a lista "reverter para
  a versão de tal data, publicada por fulano" de graça), mas o comparativo visual entre o draft atual
  e a versão vigente (diff antes de publicar) não foi especificado nesta versão da spec — avaliar se
  vale a pena para o MVP ou se fica para depois, quando houver uma versão navegável do editor.
  - **Escopo de token Sanctum por sistema × organização** (`01-modelo-de-dados.md` §5.4): o MVP
    trata qualquer sistema GIITS autenticado como confiável para atuar em qualquer organização
    ativa — revisitar se isso se provar amplo demais quando o primeiro consumidor real (fase 5)
    tiver requisitos concretos de restrição.
- **Nome dos "grupos visuais"**: chamamos de `WorkflowStep` a "etapa" que agrupa atividades no
  canvas — mas como o fluxo agora é um grafo (não sequencial), uma etapa deixa de ser
  necessariamente "um bloco que acontece antes do outro" e passa a ser mais uma organização visual
  (tipo swimlane). Vale alinhar esse nome/conceito com o usuário ao revisar `03-editor-visual.md`.
