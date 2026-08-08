# Spec: identidade visual (herdar o brandbook do `aresta.dev`)

Status: proposta, ainda não implementada. Depende de `00-visao-geral.md` (stack, fases) e toca
todas as superfícies já entregues nas fases 0-6 (Filament, editor visual Vue Flow, Inertia, login).

## 1. Objetivo

O produto foi implementado (fases 0-6) sem nenhuma identidade visual própria: Filament roda com
`Color::Amber` padrão, as páginas Inertia usam paleta genérica Tailwind (`indigo-600`/`gray-*`), a
fonte é `Instrument Sans` (default do scaffold Laravel), e não há logo em lugar nenhum. Esta spec
define como adotar **100% o brandbook oficial da Aresta** — `~/Documents/Projects/aresta.dev/
manual-identidade-aresta-full-dark.pdf` ("ARESTA // SPEC_MANUAL_V1.0") — como fonte da verdade de
cor, tipografia e logo para todas as superfícies deste produto: Filament (`/admin`), editor visual
Vue Flow, páginas Inertia (listagens, Inbox, acompanhamento de instância) e a tela de login SSO.

## 2. Fonte da verdade — e uma distinção importante dentro do próprio `aresta.dev`

O `aresta.dev` contém **duas coisas visuais diferentes** que não devem ser confundidas:

1. **A identidade "aresta"** (o guia em si): isotipo de 3 linhas paralelas, `True Dark #0D0D0D`,
   `Matrix Green #00FF66`, wordmark em `Space Grotesk`. É o que está em
   `public/img/aresta-logo*.svg`, `public/img/aresta-icon.svg`, e no PDF do manual. Esta é a
   identidade do **ecossistema/produto Aresta como um todo** — a que esta spec adota.
2. **O produto "GIITS Status"** que roda dentro do `aresta.dev` (`resources/views/status/*.blade.php`,
   `public/css/custom.css`) — usa `accent_color` **vermelho** (`#DB002F` por padrão,
   `app/Models/AppSetting.php`), configurável por instância/tenant. Isso é um recurso de
   *white-labeling* daquele produto específico (legado GIITS), não a cor da marca Aresta. Não
   replicar o vermelho aqui — o Workflow Manager não tem (e não precisa ter, por ora) branding
   customizável por organização, ver `01-modelo-de-dados.md` §5 (organização é fronteira de acesso,
   não de tema visual).

Ou seja: "usar o aresta.dev como base 100%" = adotar o brandbook (item 1), não o esquema de cor do
produto GIITS Status que por acaso mora no mesmo repositório.

## 3. Paleta de cores

Valores exatos do manual (§03 do PDF):

| Token semântico | Modo escuro (nativo) | Modo claro (alternativo) |
|---|---|---|
| Fundo geral | `True Dark` `#0D0D0D` | `Pure Light` `#FAFAFA` |
| Superfície (cards/módulos) | `Surface UI` `#1A1A1A` | `Light Surface` `#EEEEEE` |
| Texto/wordmark principal | `Pure White` `#FFFFFF` | `Ink Black` `#0D0D0D` |
| Acento (símbolo/ação) | `Matrix Green` `#00FF66` | `Deep Green` `#00AA44` |

O manual é explícito: **"rejeita paletas multi-coloridas corporativas"** e opera com um único
acento (verde) sobre neutros. Isso entra em tensão direta com uma necessidade funcional real deste
produto — distinguir visualmente 4 tipos de nó (`task`/`form`/`automated_action`/`condition`) e 3
estados de execução (`completed`/`active`/`not_reached`) no editor/acompanhamento (§7.3) — resolvida
ali com uma proposta que preserva o princípio (ver §7.3, e o item em aberto correspondente em §10).

`Matrix Green` em modo escuro serve para texto/preenchimento de botão sobre fundo escuro; em modo
claro, texto ou preenchimento sobre `#00FF66` tem contraste ruim — por isso o manual já prevê
`Deep Green #00AA44` como o acento de modo claro, não uma reinterpretação livre nossa.

## 4. Tipografia

Três famílias, papéis estritamente separados (manual §05 — violar a separação é uma das restrições
explícitas do guia, §06/EXCEPTION_02):

- **Space Grotesk (700)** — exclusivamente a wordmark do logo (`lowercase`,
  `letter-spacing: -0.03em`). Não vira fonte de UI em nenhuma tela. Já vem embutida via
  `@import` dentro do próprio SVG do logo (`aresta-logo.svg`), então não precisa ser carregada
  globalmente no `<head>` da aplicação.
- **Inter** — fonte oficial de toda a interface (dados de usuário, títulos de card, formulários,
  navegação). Substitui `Instrument Sans` como `--font-sans` em `resources/css/app.css`.
- **SF Mono / JetBrains Mono** — trechos de código, identificadores numéricos (`#PI-00123` de
  `ProcessInstance`, IDs de atividade), metadados de sistema (timestamps de auditoria em
  `04-integracao-e-notificacoes.md`). Vira `--font-mono` em `resources/css/app.css`.

## 5. Logo/isotipo

Copiar de `~/Documents/Projects/aresta.dev/public/img/` para `public/img/` deste projeto:
`aresta-logo.svg` (wordmark completo, fundo escuro), `aresta-logo-white.svg`,
`aresta-logo-black.svg` (variante para fundo claro), `aresta-icon.svg` (só o isotipo, para favicon
e espaços reduzidos). Regras de uso (manual §06, restrições de segurança da marca — aplicam-se
integralmente aqui, sem exceção):

- Nunca aplicar gradiente, textura ou sombra projetada nas linhas do isotipo ou na wordmark.
- Nunca trocar a tipografia do logotipo por fonte com serifa ou caligráfica.
- Nunca rotacionar, espelhar ou inverter a ordem de empilhamento das 3 linhas (progressão 1:2:3,
  de cima para baixo, crescente).
- Espessura das 3 linhas sempre idêntica; respiro vertical entre elas = 3× a espessura de uma linha.

`aresta-icon.svg` (isotipo puro, verde sobre transparente) vira o favicon do projeto — hoje
`public/favicon.ico` é o ícone padrão do Laravel.

## 6. Tokens Tailwind v4 (`resources/css/app.css`)

Stack já usa Tailwind v4 (`@import 'tailwindcss'` + bloco `@theme`, confirmado em
`resources/css/app.css` atual). Proposta de bloco `@theme`, substituindo o `--font-sans` atual:

```css
@import 'tailwindcss';

@theme {
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
    --font-mono: 'JetBrains Mono', 'SF Mono', ui-monospace, monospace;

    --color-true-dark: #0D0D0D;
    --color-surface: #1A1A1A;
    --color-pure-light: #FAFAFA;
    --color-light-surface: #EEEEEE;
    --color-matrix-green: #00FF66;
    --color-deep-green: #00AA44;
}
```

Isso gera `bg-true-dark`, `text-matrix-green`, `border-surface`, etc. Para alternância clara/escura
sem duplicar classes em toda a árvore de componentes, um segundo nível de tokens *semânticos* (não
literais) resolvidos via CSS custom properties, escopados a `:root`/`.dark` — mesmo padrão já
comprovado no `status/layout.blade.php` do `aresta.dev` (§8 explica onde plugar isso):

```css
:root {
    --color-bg: var(--color-pure-light);
    --color-surface-app: var(--color-light-surface);
    --color-text: var(--color-true-dark);
    --color-accent: var(--color-deep-green);
}
.dark {
    --color-bg: var(--color-true-dark);
    --color-surface-app: var(--color-surface);
    --color-text: #FFFFFF;
    --color-accent: var(--color-matrix-green);
}
```

Carregar `Inter` e `JetBrains Mono` via `@fonts`/Google Fonts (mesmo mecanismo usado hoje para
`Instrument Sans`, ou self-host se preferir evitar dependência externa — decidir em `04-integracao-
e-notificacoes.md` não se aplica aqui, é puramente uma escolha de build, ver item aberto §10).

> **Nota de implementação (já executada em parte, ver §9)**: `resources/css/app.css` implementa o
> bloco `@theme` acima com os tokens literais (`--color-true-dark` etc.) intactos, mas os tokens
> *semânticos* de §6 foram registrados com nomes diferentes dos deste documento —
> `--color-canvas`/`--color-panel`/`--color-ink`/`--color-accent`/`--color-accent-ink` no lugar de
> `--color-bg`/`--color-surface-app`/`--color-text`/`--color-accent`, e registrados dentro do
> próprio bloco `@theme` (não só em `:root`/`.dark`) para o Tailwind gerar utilities (`bg-canvas`,
> `text-ink`, `bg-panel`) que já respondem à variante `dark:` automaticamente, via
> `@custom-variant dark (&:where(.dark, .dark *))`. `app.css` é a fonte da verdade para os nomes
> exatos — este documento descreve a intenção semântica, não o identificador literal.

## 7. Aplicação por superfície

### 7.1 Shell geral (`resources/views/app.blade.php`)

Adotar o script de detecção de tema (`localStorage` + `prefers-color-scheme`, toggle com
`document.documentElement.classList.toggle('dark', ...)`) copiado do padrão já usado em
`status/layout.blade.php` do `aresta.dev` — mesma lógica, chave de storage própria (ex.
`aresta-workflow-theme`, não reaproveitar a chave `giits-status-theme` de outro produto).

### 7.2 Painel Filament (`app/Providers/Filament/AdminPanelProvider.php`)

- `->colors(['primary' => Color::hex('#00FF66')])` no lugar de `Color::Amber`.
  **Superado em 2026-08-07 (ver §11)**: a cor deixou de ser fixa no código — vem de
  `App\Models\AppSetting` (singleton, instância inteira, não por organização), editável em
  `/admin/app-settings`, caindo no `#00FF66` só quando o admin não customizou.
- `->brandLogo(asset('img/aresta-logo-white.svg'))` (ou variante clara/escura conforme o tema do
  painel) no lugar de só `->brandName('Aresta Workflow Manager')` sem logo. **Superado em
  2026-08-07 (ver §11)**: mesmo esquema — `AppSetting::logoUrl()` com fallback pro SVG da Aresta.
- `->favicon(asset('img/aresta-icon.svg'))`. **Superado em 2026-08-07 (ver §11)**: idem,
  `AppSetting::faviconUrl()` com fallback.
- Avaliar se o painel Filament roda em dark mode por padrão (`->darkMode(true)` ou equivalente da
  versão do Filament em uso) para coerência com "modo nativo" do manual — ver item aberto §10.

### 7.3 Editor visual Vue Flow (`resources/js/Components/Workflow/*.vue`)

`ActivityNode.vue` hoje usa 4 cores de fundo por tipo de nó (`blue`/`purple`/`amber`/`emerald`) e 3
cores de execução (`green`/`indigo`/`gray`) — uma paleta multi-colorida que o manual rejeita
explicitamente (§3, "rejeita paletas multi-coloridas corporativas"). Proposta de reconciliação
(**decisão de produto, não só de código — ver item aberto §10**): manter a distinção funcional
necessária (4 tipos de nó continuam precisando ser reconhecíveis à primeira vista, decisão fechada
em `00-visao-geral.md` §2 item 4 — usuário de negócio, não pode depender de legenda), mas trocar
*hue* por **forma + ícone + peso de borda**, reservando cor só para o que já é semântica de marca
(verde = acento/ação/sucesso) e para estados que realmente precisam de alerta (SLA vencido = usar
um vermelho de alerta neutro, não do brandbook — o manual não define uma cor de erro, só de acento):

- Tipos de nó: mantêm formas distintas já existentes (retângulo para `task`/`form`/
  `automated_action`, losango para `condition`), diferenciados por ícone + label, com borda neutra
  (`--color-surface`/cinza) em vez de 4 hues diferentes.
- Estado de execução (modo somente-leitura, `03-editor-visual.md` §7): `completed` = borda/preenchimento
  `matrix-green`; `active` = borda `matrix-green` pulsando; `not_reached` = opacidade reduzida sobre
  neutro, sem cor própria.
- `StepNode.vue` (container de etapa): fundo `surface`/`light-surface` conforme tema, borda
  tracejada neutra — hoje usa `indigo-500` para seleção, trocar por `matrix-green`/`deep-green`.
- Fundo do canvas Vue Flow (`Background` do `@vue-flow/background`) e `Controls`: usar
  `true-dark`/`pure-light` conforme tema, dot-grid discreto — reforça a "estética de terminal/IDE"
  que é o próprio DNA declarado da marca (manual §01).

### 7.4 Páginas Inertia gerais (`Workflows/*`, `ProcessInstances/*`, `Inbox/*`)

Substituição mecânica de paleta genérica por tokens semânticos (§6) em todas as páginas já
implementadas: `indigo-600`/`indigo-500` (botões primários, links) → `var(--color-accent)`;
`gray-*` (texto, bordas, fundos de card) → `text`/`surface-app`/`bg` semânticos. Sem mudança de
layout/estrutura nesta fase — é reskin, não redesign (ver §9, escopo explicitamente limitado a
cor/tipografia/logo, não a arquitetura de informação das telas).

### 7.5 Login SSO (`resources/views/auth/login.blade.php`)

Hoje é HTML solto com CSS inline (`background: #f5f5f7`, botão azul `#2563eb`) fora do pipeline
Vite/Tailwind. Trocar por: fundo `true-dark`, card `surface`, logo `aresta-logo-white.svg` acima do
título, botão "Entrar com Microsoft" com acento `matrix-green`. Baixo risco — tela isolada, sem
lógica além do link SSO.

### 7.6 Menu de navegação e perfil do usuário (avatar Microsoft)

Estrutura de referência: `~/Documents/Projects/aresta.dev/resources/views/status/partials/
navbar.blade.php` — cabeçalho `sticky top-0`, fundo `its-black`/`true-dark`, logo à esquerda,
ações à direita (toggle de tema + avatar do usuário com dropdown por hover/focus). Replicar essa
estrutura aqui, com uma diferença deliberada: **usar a foto real do Microsoft (`avatar_url`, já
capturado no login SSO — ver `771aa1c`), não só iniciais** — o `aresta.dev` cai em iniciais porque
não tem avatar de provedor OAuth disponível; aqui temos, então é a opção com mais fidelidade visual
e a que já está parcialmente implementada em `resources/views/home.blade.php` (padrão a reaproveitar,
não a reinventar):

```blade
@if (auth()->user()->avatar_url)
    <img class="avatar" src="{{ auth()->user()->avatar_url }}" alt="">
@else
    <div class="avatar-fallback">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
@endif
```

**Implementado.** `resources/js/Components/UserMenu.vue` (avatar `avatar_url`/fallback de iniciais,
dropdown hover/focus com nome, e-mail e "Sair" via `router.post(route('logout'))`) e
`resources/js/Components/ThemeToggle.vue` (alterna `.dark` na raiz, persiste em `localStorage` na
chave `aresta-workflow-theme`) são montados em `AppNav.vue`, logo depois dos links de navegação —
mesma posição relativa do `navbar.blade.php`. `resources/views/app.blade.php` ganhou a mesma IIFE
de detecção de tema do `aresta.dev` (§7.1) no `<head>`, para não piscar o tema errado antes do Vue
montar. `home.blade.php` deixou de ter CSS inline com hex literal e passou a carregar
`@vite(['resources/css/app.css'])`, usando os tokens semânticos (`bg-canvas`, `bg-panel`,
`text-ink`, `bg-accent`) e as duas variantes do logo (`aresta-logo-black.svg` claro/
`aresta-logo.svg` escuro, alternadas via `dark:hidden`/`dark:block`) — como é uma view Blade fora
da SPA Inertia, o toggle ali é um script vanilla idêntico ao do `aresta.dev`, não o componente Vue,
mas lê/escreve a mesma chave de `localStorage`, então o tema escolhido persiste entre a tela inicial
e o resto do produto. `auth.user` nas shared props do Inertia
(`app/Http/Middleware/HandleInertiaRequests.php`) passou a incluir `avatar_url`.

## 8. Modo claro/escuro — qual é o padrão

O manual chama o dark de **"modo nativo"** e o claro de **"modo alternativo"** (§04 do PDF,
"Renderização Padrão (Fundo Noturno)" vs. "Renderização Alternativa (Fundo Claro)") — sinal de que
a intenção da marca é o produto abrir em dark por padrão, com o claro como opção, e não o inverso.
Isso é uma mudança de comportamento perceptível para qualquer usuário atual do painel Filament
(hoje claro por padrão) — **não implementar sem validar com o usuário**, ver item aberto §10.

## 9. Escopo desta fase (7 — identidade visual)

Fases 0-6 já estão implementadas e testadas (scaffold, modelo de dados, motor de execução, editor
visual, inbox/notificações, API externa, acompanhamento read-only). Esta é a primeira mudança que
toca *todas* as telas já existentes sem adicionar funcionalidade nova — puramente cor, tipografia,
logo, favicon, e (adicionado nesta revisão, §7.6) estrutura de menu/perfil. Ordem sugerida de
execução, do menor para o maior raio de impacto:

1. Tokens base (`app.css`, favicon, cópia dos SVGs de logo) — não quebra nada, é aditivo. **Feito.**
2. Filament (`AdminPanelProvider`) — uma tela de configuração central, baixo risco. **Feito.**
3. Login SSO — tela isolada. **Feito.**
4. Tela pós-login (`home.blade.php`) e navegação entre Workflows/Instâncias/Inbox — **Feito**,
   incluindo o dropdown de perfil/avatar e o toggle de tema de §7.6.
5. Páginas Inertia gerais (listagens/Inbox/ProcessInstances) — reskin mecânico, incluindo o
   dropdown de perfil (§7.6) no `AppNav.vue` compartilhado. **Feito.**
6. Editor visual Vue Flow — o item mais delicado (§7.3), porque mexe em legibilidade funcional já
   validada, não só em cor decorativa; fazer por último e testar com um grafo real de cada tipo de
   nó antes de considerar concluído. **Feito** (`ActivityNode.vue`/`StepNode.vue` já usam os tokens
   semânticos — `border-accent`, `bg-panel`, `text-ink` etc. — no lugar das 4 hues antigas).

Fase 7 completa: todos os itens de escopo (§1-§7.6) implementados e verificados manualmente
(build de produção, suíte de testes, e navegação real via login de desenvolvimento — avatar
Microsoft, dropdown, toggle de tema persistindo entre `home.blade.php` e as páginas Inertia, e
logout). Os itens em aberto remanescentes (§10) são decisões de produto, não trabalho pendente.

## 9.1 Alinhamento ao starter kit (2026-07-25)

A pedido do usuário, o visual foi realinhado à reformulação mais recente do brandbook, publicada no
`aresta_starter_kit` (`~/Documents/Projects/aresta_starter_kit/docs/brand/` — `tokens.css`,
`components/` e o RFC `2026-07-25-reformulacao-visual-componentes.md` de lá). O que mudou aqui:

- **Dark por padrão** ("a Aresta é dark-native"): `app.blade.php`/`home.blade.php` aplicam `.dark`
  quando não há escolha salva (antes caía no `prefers-color-scheme`), e o Filament ganhou
  `->defaultThemeMode(ThemeMode::Dark)`. Resolve o primeiro item em aberto de §10.
- **Cores de estado calibradas WCAG AA** (derivadas do kit, não do manual): `--color-danger`/
  `--color-warning`/`--color-success` semânticos por tema em `app.css` (claro `#B42318`/`#854D0E`/
  `#00702E`; escuro `#F97066`/`#FACC15`/Matrix Green), mais os crus `*-raw` para fundos/bordas.
  Substituem os `red-600`/`amber-*` genéricos nas páginas Vue e resolvem o item "cor de alerta/erro"
  de §10.
- **Escala de raios do kit** mapeada nos tokens do Tailwind (`rounded-lg` 12px = controles,
  `rounded-xl` 16px = cards, `rounded-2xl` 20px = destaques/modais) e **sombras em duas camadas**
  (`shadow-sm`/`shadow-lg` → `--aresta-shadow*`, calibradas por tema).
- **Navbar sempre escura** (`AppNav.vue` + `ThemeToggle.vue` + `UserMenu.vue`): fundo Surface UI
  fixo, sticky, verde só na interação, e-mail do dropdown em fonte mono, "Sair" em vermelho de
  estado — só o conteúdo abaixo dela reage ao tema, como em `components/navbar.html`.
- **Login no padrão `components/login.html`**: sempre escuro, glow radial verde discreto, card
  20px com sombra ambiente, botão SSO translúcido que ganha borda verde no hover/foco, metadado
  `sso://microsoft-entra-id` em mono.
- **Scrollbar fina nas variáveis semânticas e anel de foco em halo translúcido** (em vez do
  outline padrão) em `app.css`.

## 10. Itens em aberto

- **Dark como padrão (§8)**: ~~confirmar com o usuário~~ **Resolvido em §9.1** — o produto abre em
  modo escuro por padrão, seguindo o starter kit ("a Aresta é dark-native"); o claro permanece via
  toggle, persistido por usuário.
- **Paleta funcional do editor (§7.3)**: já implementada (`ActivityNode.vue`/`StepNode.vue` nos
  tokens semânticos, ver §9) — validar com uso real se a distinção por forma+ícone (sem hue por
  tipo de nó) continua legível na prática; se não se provar suficiente, preferir um segundo acento
  de baixa saturação a reintroduzir 4 hues saturadas.
- **Seletor de organização no menu**: o `aresta.dev` tem um trocador de contexto no header (dropdown
  de "quadro" em `navbar.blade.php`, linhas 11-27) para usuário com acesso a mais de um quadro. Este
  produto tem o equivalente estrutural — um usuário pode pertencer a mais de uma `Organization`
  (`home.blade.php` já lista todas em `<ul class="orgs">`, mas é só exibição, sem trocar contexto
  ativo em nenhuma tela depois do login) — decidir com o usuário se `Workflows`/`ProcessInstances`/
  `Inbox` precisam de um seletor de organização no menu (análogo ao seletor de quadro) ou se
  cada usuário sempre está implicitamente numa única organização ativa e isso nunca vira UI.
- **Cor de alerta/erro**: **Resolvido em §9.1** — adotadas as derivadas do starter kit (calibradas
  por tema para AA: `#B42318`/`#F97066` erro, `#854D0E`/`#FACC15` aviso), usadas com moderação, não
  como "segundo acento" da marca.
- **Hospedagem de fonte**: `Inter`/`JetBrains Mono` via Google Fonts (like `aresta.dev` já faz para
  `Inter`) vs. self-host (`npm` + `@fontsource/*`, evita dependência de terceiro em produção) — decidir
  ao implementar §6, não bloqueia o resto da spec.
- **Branding por organização**: esta spec assumia cor de marca fixa (§7.2), coerente com a decisão
  fechada de que `organization_id` é fronteira de acesso, não de tema (`01-modelo-de-dados.md` §5).
  **Parcialmente superado em 2026-08-07 (ver §11)**: a pedido do usuário, nome/logo/favicon/cor
  passaram a ser configuráveis — mas como um singleton **global de instância** (`AppSetting`,
  igual a `AiProviderSetting`), não por `Organization`. White-label por organização-cliente
  continua não implementado e, se demandado, ainda é spec nova — a distinção "fronteira de acesso,
  não de tema" continua valendo para `organization_id` especificamente.

## 11. Atualização 2026-08-07 — identidade configurável via `/admin/app-settings`

A pedido do usuário, a marca deixou de ser fixa em código: `App\Models\AppSetting` (singleton,
`id=1`, tabela `app_settings`) guarda `app_name`/`primary_color`/`logo_path`/`favicon_path`,
editável em `/admin/app-settings` (`App\Filament\Pages\AppSettings`, restrito a `platform-staff` —
mesmo critério de `OrganizationPolicy`, já que é config de instância inteira, não organizacional,
ver item "Branding por organização" em §10). Sem customização, cai exatamente nos valores que esta
spec definia como fixos (§3/§5/§7.2) — a mudança é aditiva, não uma remoção da identidade Aresta
como padrão.

Superfícies atualizadas:

- **Filament (`AdminPanelProvider`)** — `brandName`/`brandLogo`/`darkModeBrandLogo`/`favicon`/
  `colors.primary` lidos de `AppSetting::current()` a cada request, com fallback pros valores da
  Aresta (§7.2).
- **Produto inteiro (Inertia/Blade)** — decisão do usuário foi que a cor/nome também valem fora do
  Filament, não só no painel administrativo:
  - `app.blade.php` (raiz Inertia) resolve `<title>`, favicon e injeta um `<style>` sobrescrevendo
    `--color-accent`/`--color-accent-ink` (tokens de §6/§8) **só quando `primary_color` não é
    null** — ou seja, sem customização, o claro/escuro calibrado AA do starter kit (§9.1) continua
    intacto; só passa a usar a cor do admin depois que ele efetivamente salva uma.
  - `HandleInertiaRequests::share()` expõe `branding.app_name`/`branding.logo_url` pras páginas Vue
    (`AppNav.vue`, `Home.vue`, `Auth/Login.vue`, `AppFooter.vue`), substituindo o `appName` que
    antes vinha hardcoded de `config('app.name')` em cada controller.
  - `AppNav.vue`/`ThemeToggle.vue` trocaram as classes literais `text-matrix-green`/
    `border-matrix-green` (hue fixo da marca) por `text-accent`/`border-accent` (token semântico),
    pra que o realce de navegação também responda à cor customizada — sem isso, o override de
    `--color-accent` não alcançava a navbar.
  - `Login.vue` trocou o hex literal `#00FF66` (glow de fundo, borda no hover) por
    `var(--color-accent)`.
- **Editor visual Vue Flow (§7.3)** — **não tocado deliberadamente**: as cores de estado de
  execução (`completed`/`active` em `matrix-green`) continuam fixas na marca, porque ali a cor é
  semântica de status ("sucesso"), não de identidade — customizar isso junto teria misturado dois
  conceitos diferentes por trás do mesmo botão de "cor principal".

Upload de logo/favicon usa o disco `public` (`storage/app/public` → `public/storage`, symlink via
`php artisan storage:link --force`, adicionado ao `deploy.sh` e ao `README.md`).
