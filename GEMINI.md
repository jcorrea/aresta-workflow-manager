# Aresta Workflow Manager — Instruções do Projeto

Este é o **Aresta Workflow Manager**, uma plataforma de modelagem e execução de processos de negócio com editor visual. É um produto novo e independente da suíte Aresta, inspirado no módulo de workflow do legado `giits-propostas` (PHP proprietário, não Laravel).

## Status atual
Fase 0 (scaffold) já implementada. Laravel 13, Podman, Filament, SSO Microsoft e isolamento multi-tenant (organizações + RBAC com `spatie/laravel-permission`) já existem.

Antes de implementar qualquer coisa nova, leia nesta ordem:

1. `docs/specs/00-visao-geral.md`
2. `docs/specs/01-modelo-de-dados.md`
3. `docs/specs/02-motor-de-execucao.md`
4. `docs/specs/03-editor-visual.md`
5. `docs/specs/04-integracao-e-notificacoes.md`
6. `docs/specs/05-identidade-visual.md`

Cada spec termina com “itens em aberto”. Trate isso como decisão pendente de validação com o usuário, não como suposição para resolver sozinho.

## Stack
- Laravel 13 / PHP 8.3+
- Vue 3 + Inertia + Vue Flow apenas para o editor visual
- Filament para CRUD administrativo em `/admin`
- MySQL em dev/prod, SQLite em memória para testes
- Laravel Socialite + SSO Microsoft / Entra ID
- Laravel Sanctum para API externa
- `spatie/laravel-permission` para RBAC administrativo
- Dev via Podman, com scripts `bin/composer`, `bin/artisan`, `bin/php`, `bin/serve`

## Regras de domínio
- **Configuração e execução são separadas.** `Workflow`/`WorkflowVersion`/`WorkflowStep`/`WorkflowActivity`/`WorkflowTransition` representam desenho. `ProcessInstance` e afins representam execução. Nunca misture os dois lados na mesma tabela.
- **Fim do processo é `is_end`.** Não reintroduza IDs mágicos.
- **Join é sempre AND.** Nunca tornar isso configurável. O editor deve bloquear grafo inválido na origem.
- **Condições não usam `eval`.** `condition_expression` deve ser JSON estruturado e avaliado por interpretador próprio simples.
- **Isolamento entre organizações é segurança real.** `organization_id` é obrigatório em entidades organizacionais. Sempre aplicar global scope, policy e teste de vazamento entre organizações.
- **Versionamento:** apenas `draft` e `published`. Uma versão publicada nunca volta a outro estado. Existe no máximo um draft por workflow. `ProcessInstance` nunca migra de versão.
- **Roles não são apagados fisicamente** se já foram referenciados; devem ser desativados com `active = false`.

## Rodando localmente
Use `bin/` para tarefas que não dependem de MySQL real. Para qualquer ação que precise do banco de dev, use o container do compose. Não assuma que comandos fora do compose enxergam o MySQL.

## Testes
- Use `./bin/artisan test` para a suíte.
- Em features de domínio, cobre o isolamento entre organizações.
- Na Fase 2, teste `WorkflowEngine` via factories de grafo, sem depender do editor visual.

## Estilo de trabalho
- Siga a ordem das fases definida nas specs.
- Não implemente “flexibilidade” que contradiga decisões já fechadas.
- Se uma mudança conflitar com decisão explícita das specs, sinalize isso claramente.
- Prefira mudanças pequenas e testáveis.
- Antes de codar algo novo, leia o trecho relevante das specs.

## Resposta esperada
- Seja direto.
- Prefira diffs pequenos, migrations explícitas e testes junto da mudança.
- Quando houver alternativa de implementação, proponha a mais segura e consistente com as specs.
