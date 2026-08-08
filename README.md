# Aresta Workflow Manager

Plataforma para modelar e executar processos de negócio (workflows) com um **editor visual de
arrastar-e-conectar**: desenhe etapas e atividades num canvas, conecte-as formando um grafo (com
ramificações, condições e caminhos paralelos), publique o processo e acompanhe instâncias reais
rodando sobre ele.

Projeto novo e independente, inspirado no conceito de processo/etapa/atividade do módulo de
workflow do sistema legado `giits-propostas` (config vs. instância, template versionável), mas com
stack própria e um motor de execução baseado em grafo (DAG) — o legado só suporta fluxo linear.

**Status: Fase 0 (scaffold) implementada** — Laravel 13, Podman, Filament, SSO Microsoft e o
isolamento multi-tenant (organizações/RBAC) já existem. O modelo de dados do processo em si
(`Workflow`/`WorkflowVersion`/...), o motor de execução e o editor visual ainda não foram
implementados. Ver a spec completa em [`docs/specs/`](docs/specs/), começando por
[`00-visao-geral.md`](docs/specs/00-visao-geral.md) (§8 tem o plano de fases).

## Documentação

- [`docs/specs/00-visao-geral.md`](docs/specs/00-visao-geral.md) — objetivo, decisões de produto,
  comparação com o legado, arquitetura de alto nível, stack, plano de fases.
- [`docs/specs/01-modelo-de-dados.md`](docs/specs/01-modelo-de-dados.md) — entidades, migrations,
  versionamento de processo.
- [`docs/specs/02-motor-de-execucao.md`](docs/specs/02-motor-de-execucao.md) — motor de execução,
  fork/join, condições, SLA.
- [`docs/specs/03-editor-visual.md`](docs/specs/03-editor-visual.md) — canvas Vue Flow, tipos de nó,
  builder de condição, modo de acompanhamento.
- [`docs/specs/04-integracao-e-notificacoes.md`](docs/specs/04-integracao-e-notificacoes.md) —
  permissões, notificações, API para sistemas externos.
- [`docs/specs/05-identidade-visual.md`](docs/specs/05-identidade-visual.md) — identidade visual
  (herdada do brandbook do `aresta.dev`): cores, tipografia, logo.
- [`docs/specs/06-refinamento-ia-editor.md`](docs/specs/06-refinamento-ia-editor.md) — refinamento
  e edição de workflows assistidos por Inteligência Artificial no editor visual.
- [`docs/manual-editor-visual.md`](docs/manual-editor-visual.md) — manual de uso do editor visual
  (não é spec, é o "como usar" pra quem for desenhar um processo).

## Stack

- Laravel 13 / PHP 8.3+
- Vue 3 + Inertia + Vue Flow (`@vue-flow/core`) para o editor visual — **ainda não instalado**,
  chega na Fase 3
- MySQL (dev/produção) / SQLite em memória (testes)
- Filament 5 (back-office administrativo, `/admin`)
- Laravel Socialite + SSO Microsoft (Azure AD/Entra ID) — única forma de login, sem tela própria do
  Filament
- Laravel Sanctum (pacote instalado; endpoints da API para sistemas externos chegam na Fase 5)
- `spatie/laravel-permission`, com o recurso de *teams* mapeado para `organization_id` (RBAC
  administrativo escopado por organização — `workflow-admin`/`editor`/`viewer`/`platform-staff`)
- Ambiente de dev via Podman, sem exigir PHP no host — mesmo padrão do outro projeto da suíte Aresta
  (GIITS Status, em `~/Documents/Projects/aresta.dev`)

## Rodando localmente

Não é necessário ter PHP instalado no host — os scripts em `bin/` rodam tudo dentro de um container
Podman com PHP 8.3 e as extensões necessárias (`pdo_mysql`, `pdo_sqlite`, `gd`, `bcmath`, `intl`,
`zip`, `opcache`).

```bash
# build da imagem local (uma vez, ou sempre que .docker/php/Containerfile mudar)
podman build -t aresta-workflow-php -f .docker/php/Containerfile .

cp .env.example .env   # preencha AZURE_CLIENT_ID/AZURE_CLIENT_SECRET/AZURE_TENANT_ID
./bin/composer install
./bin/artisan key:generate

# banco MySQL de dev + a própria app, via docker-compose (rede compartilhada entre os dois)
docker compose up -d app mysql
docker compose exec app php artisan migrate --seed

# symlink storage/app/public → public/storage — necessário pro logo/favicon enviados em
# /admin/app-settings ficarem acessíveis via HTTP
docker compose exec app php artisan storage:link
```

A app sobe em `http://localhost:8000`. Scripts disponíveis em `bin/` (rodam isolados, fora da rede
do compose — úteis para tudo que não precisa do MySQL, como testes, Pint ou instalar pacotes):

- `bin/composer` — roda o Composer dentro do container
- `bin/artisan` — roda `php artisan ...` dentro do container (`bin/artisan test`, por exemplo, roda
  contra SQLite em memória e não precisa do MySQL do compose)
- `bin/php` — roda `php ...` dentro do container
- `bin/pint` — roda o Laravel Pint (`./vendor/bin/pint`) para formatação de código
- `bin/serve` — sobe `php artisan serve` isolado (sem o MySQL do compose — útil só para checagens
  rápidas que não tocam banco; para o fluxo completo, prefira `docker compose up -d app mysql`)

### Assets e Frontend (Vite)

Para instalar as dependências de frontend e rodar o servidor de desenvolvimento do Vite:

```bash
npm install
npm run dev
```

Se preferir um PHP 8.3+ já instalado no host (com as extensões acima), pode ignorar os scripts
`bin/*` e rodar `composer`/`php artisan` diretamente.

### SSO Microsoft (Azure AD / Entra ID)

Configure um App Registration no Azure, ajustando (ou adicionando) a Redirect URI:

```
http://localhost:8000/auth/azure/callback   (dev)
https://<seu-dominio>/auth/azure/callback   (produção)
```

e preenchendo `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET` e `AZURE_TENANT_ID` em `.env`.

### Painel administrativo (Filament)

Disponível em `/admin`, sem tela de login própria — como o login é exclusivo via SSO, é preciso
entrar primeiro pela tela principal (`/login`) e depois acessar `/admin` com a mesma sessão. Só
acessa quem pertence a pelo menos uma organização, ou tem o papel `platform-staff`
(`app/Models/User.php::canAccessPanel()`).

### Organizações e RBAC

Toda organização nova (`Organization`) provisiona automaticamente os papéis `workflow-admin`,
`workflow-editor` e `workflow-viewer` escopados a ela (`app/Observers/OrganizationObserver.php`).
`platform-staff` é o único papel global (ITS Group, suporte/operação através de qualquer
organização) — ver `docs/specs/01-modelo-de-dados.md` §5.

## Testes

```bash
./bin/artisan test
```

Roda contra SQLite em memória (`phpunit.xml`), independente do MySQL do `docker-compose.yml`. Cobre
os fluxos da Fase 0: redirecionamento de visitante não autenticado, login/criação de usuário via SSO
Microsoft, acesso ao painel Filament e o isolamento entre organizações (`OrganizationScope`).
