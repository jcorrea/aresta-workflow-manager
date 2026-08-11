# Spec: Refinamento e Edição de Workflow com Inteligência Artificial

Status: em especificação/projeto.

---

## 1. Objetivo

Permitir que usuários e analistas de negócio editem e aprimorem versões em rascunho (`WorkflowVersion` com `status = draft`) diretamente no editor visual utilizando Inteligência Artificial. 

Em vez de refazer o fluxo do zero ou mover dezenas de nós manualmente, o usuário poderá abrir o modal de refinamento por IA, visualizar/editar a descrição base do processo e inserir comentários/instruções em linguagem natural (ex.: *"Adicionar uma etapa de aprovação da Diretoria quando o valor for superior a R$ 50 mil"*, *"Trocar o responsável da verificação para a equipe de Compliance"*).

---

## 2. Requisitos Funcionais

1. **Ação no Editor Visual (`Edit.vue`)**:
   - Adicionar o botão **"Ajustar com IA"** na barra de ferramentas do editor visual de workflows.
   - O botão só fica ativo para versões em rascunho (`draft`).

2. **Modal de Refinamento de IA (`AiRefineModal.vue`)**:
   - Exibe a **descrição do processo** (editável).
   - Campo de texto para **Instruções de melhoria / Comentários** em linguagem natural.
   - Botão **"Refinar Workflow com IA"** com indicação de carregamento (`processing`).

3. **Atalho "Corrigir Erros com IA" a partir dos problemas do `WorkflowGraphValidator`**:
   - Quando a versão em rascunho tem pelo menos um problema de severidade `error` (`Edit.vue`
     já recebe esses problemas via `issues`, calculados pelo `WorkflowGraphValidator` a cada
     carregamento da tela — ver `03-editor-visual.md` §6), o botão da toolbar troca de
     **"Ajustar com IA"** para **"Corrigir Erros com IA"** (estilo em tom de erro em vez do tom de
     destaque padrão).
   - Ao abrir o modal nesse estado, o campo de instruções já vem **pré-preenchido** com a lista das
     mensagens de erro (`GraphIssue.message`, ex.: *"O nó #45 não tem nenhuma conexão de
     entrada..."*) formatada como uma instrução de correção — mas continua **editável**: o usuário
     revisa/ajusta o texto e precisa confirmar o envio manualmente, não há disparo automático.
   - Não existe endpoint ou fluxo de refinamento separado para esse caso — é o mesmo
     `POST .../refine-ai` de sempre, só que com o campo `instructions` pré-populado no frontend.
   - A confirmação de que os erros foram corrigidos não exige nenhuma revalidação especial: o
     `refineWithAi()` já redireciona de volta para `edit()` (§3.1), que roda o
     `WorkflowGraphValidator` de novo e recarrega a lista de problemas — se algo não foi resolvido,
     o usuário vê e pode tentar de novo ou editar manualmente.

4. **Inteligência no Refinamento (`AiWorkflowDraftRefiner`)**:
   - O serviço recebe:
     - O rascunho atual (`WorkflowVersion` e seu payload JSON retornado por `toGraphPayload()`).
     - A lista de papéis de negócio cadastrados para a organização (`Role`).
     - A descrição atualizada do processo + os comentários de melhoria do usuário.
   - O modelo de IA (Google Gemini / Azure OpenAI / Ollama / OpenRouter) reestrutura o grafo de etapas, atividades e transições.
   - **Extração e Auto-criação de Papéis**: Se o comentário de melhoria citar novos responsáveis/papéis que não existem na organização (ex.: "Compliance", "Diretoria Jurídica"), o serviço cria automaticamente esses papéis para a organização (`Role::create`) e os vincula às atividades geradas.

5. **Persistência Atômica**:
   - O refinamento executa dentro de uma transação no banco de dados (`DB::transaction`). Em caso de falha de validação ou recusa da IA, o rascunho anterior permanece intacto.
   - O layout das caixas no canvas é organizado automaticamente (esquerda para a direita) mantendo a visualização limpa.

---

## 3. Arquitetura e Componentes

### 3.1 Rota e Controller
- **Endpoint**: `POST /workflows/{workflow}/versions/{version}/refine-ai`
- **Controller**: `WorkflowVersionController::refineWithAi()`
- **Autorização**: `Gate::authorize('update', $workflow)` e validação de `version.status === draft`.

### 3.2 Serviço `AiWorkflowDraftRefiner`
Usa o mesmo mecanismo de provedores e overrides do `AiWorkflowDraftGenerator`, enviando ao LLM:
- O grafo atual (etapas, atividades, transições).
- Os papéis disponíveis na organização.
- As instruções de alteração fornecidas pelo usuário.

### 3.3 Pré-preenchimento do atalho de correção (§2, item 3)
Puramente no frontend, sem impacto no contrato da API: `Edit.vue` monta o texto de
`instructions` a partir de `errorIssues` (prop `issues`, já filtrada por `severity === 'error'`) e
passa via prop `initial-instructions` para `AiRefineModal.vue`, que usa esse valor como estado
inicial do campo (em vez de sempre abrir vazio) quando o modal é aberto.

---

## 4. Atualização da Documentação e README

Este documento passa a integrar a suíte oficial de especificações técnicas do projeto em `docs/specs/06-refinamento-ia-editor.md`.
