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

## 7. Aplicação por superfície

### 7.1 Shell geral (`resources/views/app.blade.php`)

Adotar o script de detecção de tema (`localStorage` + `prefers-color-scheme`, toggle com
`document.documentElement.classList.toggle('dark', ...)`) copiado do padrão já usado em
`status/layout.blade.php` do `aresta.dev` — mesma lógica, chave de storage própria (ex.
`aresta-workflow-theme`, não reaproveitar a chave `giits-status-theme` de outro produto).

### 7.2 Painel Filament (`app/Providers/Filament/AdminPanelProvider.php`)

- `->colors(['primary' => Color::hex('#00FF66')])` no lugar de `Color::Amber`. Como não há
  branding por organização neste produto (§2), a cor é fixa no código, não vinda de um
  `AppSetting` dinâmico como no `aresta.dev`.
- `->brandLogo(asset('img/aresta-logo-white.svg'))` (ou variante clara/escura conforme o tema do
  painel) no lugar de só `->brandName('Aresta Workflow Manager')` sem logo.
- `->favicon(asset('img/aresta-icon.svg'))`.
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
logo, favicon. Ordem sugerida de execução, do menor para o maior raio de impacto:

1. Tokens base (`app.css`, favicon, cópia dos SVGs de logo) — não quebra nada, é aditivo.
2. Filament (`AdminPanelProvider`) — uma tela de configuração central, baixo risco.
3. Login SSO — tela isolada.
4. Páginas Inertia gerais (listagens/Inbox/ProcessInstances) — reskin mecânico.
5. Editor visual Vue Flow — o item mais delicado (§7.3), porque mexe em legibilidade funcional já
   validada, não só em cor decorativa; fazer por último e testar com um grafo real de cada tipo de
   nó antes de considerar concluído.

## 10. Itens em aberto

- **Dark como padrão (§8)**: confirmar com o usuário se o produto deve abrir em modo escuro por
  padrão (alinhado ao manual, "modo nativo") ou se mantém claro por padrão com dark como opção via
  toggle — impacta Filament e todo o shell Inertia, não é um detalhe cosmético isolado.
- **Paleta funcional do editor (§7.3)**: validar a proposta de trocar hue-por-tipo por forma+ícone
  com o usuário antes de reimplementar `ActivityNode.vue` — é uma mudança de legibilidade para quem
  já usa o editor, não só de marca; se a distinção por cor se provar necessária na prática (feedback
  de uso real), preferir um segundo acento de baixa saturação a reintroduzir 4 hues saturadas.
- **Cor de alerta/erro**: o manual não define uma cor de erro/aviso (só o acento verde) — precisa de
  uma decisão explícita para SLA vencido, validação de grafo com erro (`03-editor-visual.md` §6) e
  estados de falha em geral. Proposta: um vermelho neutro fora da paleta de marca (ex. `#EF4444`,
  já comum em UI de erro), usado com moderação, não como um "segundo acento" da marca.
- **Hospedagem de fonte**: `Inter`/`JetBrains Mono` via Google Fonts (like `aresta.dev` já faz para
  `Inter`) vs. self-host (`npm` + `@fontsource/*`, evita dependência de terceiro em produção) — decidir
  ao implementar §6, não bloqueia o resto da spec.
- **Branding por organização**: esta spec assume cor de marca fixa (§7.2), coerente com a decisão
  fechada de que `organization_id` é fronteira de acesso, não de tema (`01-modelo-de-dados.md` §5).
  Se no futuro surgir demanda de white-label por cliente (como o `accent_color` do GIITS Status),
  isso é uma spec nova, não uma extensão silenciosa desta.
