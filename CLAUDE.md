# Instruções do projeto

Este é o **Aresta Workflow Manager** — plataforma de modelagem e execução de processos de negócio
com editor visual (canvas de arrastar-e-conectar). Produto novo e independente da suíte Aresta,
inspirado (não portado) no módulo de workflow do legado `giits-propostas`
(`~/Documents/Projects/giits-propostas`, framework PHP proprietário, não Laravel).

**Status atual: Fase 0 (scaffold) implementada.** Laravel 13, Podman, Filament, SSO Microsoft e o
isolamento multi-tenant (organizações + RBAC via `spatie/laravel-permission`) já existem — ver
"Rodando localmente" abaixo. O modelo de dados do processo (`Workflow`/`WorkflowVersion`/...), o
motor de execução e o editor visual ainda não foram implementados, só especificados em
`docs/specs/`. Antes de implementar qualquer coisa nova, ler nesta ordem:

1. `docs/specs/00-visao-geral.md` — objetivo, decisões de produto já tomadas em entrevista com o
   usuário, comparação com o legado, arquitetura, stack, plano de fases.
2. `docs/specs/01-modelo-de-dados.md` — entidades e migrations propostas.
3. `docs/specs/02-motor-de-execucao.md` — motor de execução (fork/join/condição/SLA).
4. `docs/specs/03-editor-visual.md` — editor Vue Flow.
5. `docs/specs/04-integracao-e-notificacoes.md` — permissões, notificações, API externa.

Cada arquivo de spec termina com uma seção "itens em aberto" — são decisões que ainda precisam ser
validadas com o usuário antes ou durante a implementação da fase correspondente, não suposições para
resolver sozinho silenciosamente.

## Stack planejada (ver `docs/specs/00-visao-geral.md` §5 para justificativa)

- Laravel 13 / PHP 8.3+
- Vue 3 + Inertia + Vue Flow (`@vue-flow/core`) — só para o editor visual; o resto do produto
  (CRUD administrativo) usa Filament.
- MySQL (dev/produção), SQLite em memória (testes) — mesmo padrão do GIITS Status.
- Filament 5 em `/admin`.
- Laravel Socialite + SSO Microsoft (Azure AD/Entra ID) — sem tela de login própria do Filament.
- Laravel Sanctum (API para sistemas externos: giits-propostas, giits-api).
- `spatie/laravel-permission` (RBAC administrativo — distinto da atribuição de responsável por
  atividade, que não é RBAC genérico, ver `04-integracao-e-notificacoes.md` §2).
- Ambiente de dev via Podman, scripts `bin/composer`/`bin/artisan`/`bin/php`/`bin/serve`, mesmo
  padrão do outro projeto da suíte Aresta (`~/Documents/Projects/aresta.dev`, GIITS Status) — copiar
  o `Containerfile` de lá como ponto de partida na Fase 0, ajustando extensão `pdo_mysql`.

## Rodando localmente

Sem PHP no host: os scripts em `bin/` (`bin/composer`, `bin/artisan`, `bin/php`, `bin/serve`) rodam
tudo dentro de um container Podman com PHP 8.3 (`.docker/php/Containerfile`). Fluxo completo (com
MySQL de dev):

```bash
podman build -t aresta-workflow-php -f .docker/php/Containerfile .
cp .env.example .env   # preencha AZURE_CLIENT_ID/AZURE_CLIENT_SECRET/AZURE_TENANT_ID
./bin/composer install
docker compose up -d app mysql
docker compose exec app php artisan migrate --seed
```

Detalhes (SSO Microsoft, painel Filament, provisionamento de organização) na "Rodando localmente" do
`README.md`. `bin/artisan`/`bin/composer`/`bin/php` fora do compose não enxergam o MySQL do
compose (redes Podman separadas) — servem para o que não precisa de banco real (`bin/artisan test`,
que usa SQLite em memória, Pint, instalar pacotes); para qualquer coisa que precise do MySQL de dev,
use `docker compose exec app ...`.

## Testes

```bash
./bin/artisan test
```

Roda contra SQLite em memória (`phpunit.xml`), mesmo padrão do GIITS Status. A Fase 0 já cobre:
redirecionamento de visitante não autenticado, login/criação de usuário via SSO Microsoft
(`Socialite::shouldReceive`), acesso ao painel Filament (`canAccessPanel`) e o isolamento entre
organizações (`App\Models\Scopes\OrganizationScope`, testado via um model de fixture em
`tests/Fixtures` — Fase 0 ainda não tem nenhum model de domínio real para exercitar o scope).

Ao chegar na Fase 2 (motor de execução): testar `WorkflowEngine` inteiramente via factories de
grafo, sem depender do editor visual — ver `docs/specs/02-motor-de-execucao.md` §6 para os casos
mínimos (linear, fork, decisão, join, ciclo, ação automática).

## Pontos de atenção do domínio

- **Config vs. instância**: `Workflow`/`WorkflowVersion`/`WorkflowStep`/`WorkflowActivity`/
  `WorkflowTransition` são o *desenho* (só `draft` é editável); `ProcessInstance` e afins são a
  *execução*, sempre amarrada a uma `WorkflowVersion` publicada e imutável. Nunca misturar os dois
  lados numa única tabela — é o padrão que fazia sentido no legado e continua fazendo sentido aqui.
- **`is_end` em vez de magic number**: o legado usa `idprocessdestiny === 4` hardcoded para "fim do
  processo". Aqui isso é um campo booleano nomeado (`workflow_activities.is_end`) — não reintroduzir
  esse tipo de acoplamento a IDs de linha de tabela lookup.
- **Join é sempre AND, nunca configurável, decisão fechada (2026-07-16)**: múltiplas transições
  `always` saindo de um nó = paralelo (todas ativam); um nó com múltiplos predecessores só ativa
  quando todos completarem. Isso só é seguro porque o editor visual (fase 3) **proíbe no desenho**
  qualquer bloco fork/join mal-formado, via `WorkflowGraphValidator` (análise de pós-dominância —
  `docs/specs/02-motor-de-execucao.md` §3.3.1). Não reintroduzir uma tentativa de "adivinhar" no
  motor de execução se um predecessor "nunca vai chegar" — isso é exatamente o tipo de esperteza em
  runtime que a decisão rejeitou a favor de bloquear o problema na origem (no editor).
- **Condição sem `eval`/expression-language**: `condition_expression` é JSON estruturado
  (`field`/`operator`/`value`, compostos via `all`/`any`), avaliado por um interpretador próprio
  simples — nunca introduzir uma linguagem de expressão textual arbitrária aqui, o público-alvo do
  editor é administrador de negócio, não desenvolvedor (`docs/specs/00-visao-geral.md` §2, item 4).
- **Isolamento entre organizações é fronteira de acesso real, não filtro de UI — decisão fechada
  (2026-07-16)**: `Organization` = empresa-cliente externa (`docs/specs/01-modelo-de-dados.md` §5).
  `organization_id` é **obrigatório** (não nullable) em `workflows`, `roles` e `process_instances` —
  não existe "processo global compartilhado entre clientes". Todo model organizacional usa global
  scope + policy, e o RBAC administrativo usa o recurso de *teams* do `spatie/laravel-permission`
  mapeado para `organization_id` (`docs/specs/04-integracao-e-notificacoes.md` §2). Ao escrever
  qualquer query/controller/policy novo que toque `Workflow`/`ProcessInstance`/`Role`, tratar
  vazamento entre organizações como bug de segurança, não como detalhe a refinar depois — e cobrir
  isso em teste (usuário da organização A não pode ver/acessar registro da organização B), não só o
  caminho feliz.
- **Versionamento: um draft por vez, instância nunca migra, rollback é reapontar — decisão fechada
  (2026-07-16, `docs/specs/01-modelo-de-dados.md` §2.2)**: `workflow_versions.status` só tem `draft`
  e `published` (não existe `archived`) — uma versão publicada nunca muda de status de novo; "qual é
  a vigente" é só o ponteiro `workflows.current_published_version_id`, nunca um campo de status. Não
  reintroduzir um terceiro status nem um campo "is_current" na própria versão. Constraint de "no
  máximo um draft por workflow" é reforçada no banco (coluna `draft_lock_workflow_id` nullable+unique,
  truque necessário porque MySQL não tem índice único parcial) — não confiar só em validação da
  aplicação para isso, é uma condição de corrida real. `ProcessInstance` nunca migra de
  `workflow_version_id` depois de criada, nem por publish nem por rollback — não construir esse
  recurso "para ser flexível", foi uma escolha deliberada pelo risco de o grafo ter mudado de forma
  incompatível com uma atividade já em andamento.
- **`roles` nunca é apagado fisicamente, só desativado — decisão fechada (2026-07-16,
  `docs/specs/01-modelo-de-dados.md` §2.6.1)**: `roles.active = false` no lugar de `DELETE`, sempre
  que o role já foi referenciado por qualquer `workflow_activities` (draft ou published) ou
  `process_instance_activities` (mesmo histórica) — nunca implementar um botão "excluir role" que
  apague a linha de verdade nesses casos. Nome de role **não** tem snapshot (sempre mostra o nome
  atual, mesmo em telas de histórico) e elegibilidade de `role_user` é **sempre** resolvida ao vivo
  (nunca fixada no momento em que a atividade nasceu) — não introduzir cache/snapshot de composição
  de role "para performance" sem entender que isso quebra essa decisão.

## Estilo de resposta

- Projeto ainda sem usuários em produção — pode propor mudanças de estrutura na spec livremente,
  mas sinalizar claramente quando uma mudança contradiz uma decisão já tomada em entrevista com o
  usuário (`docs/specs/00-visao-geral.md` §2) em vez de sobrescrever silenciosamente.
- Ao implementar, seguir a ordem de fases de `docs/specs/00-visao-geral.md` §8 — o motor de execução
  (fases 1-2) precisa estar correto e testado antes de investir no editor visual (fase 3).
