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

3. **Inteligência no Refinamento (`AiWorkflowDraftRefiner`)**:
   - O serviço recebe:
     - O rascunho atual (`WorkflowVersion` e seu payload JSON retornado por `toGraphPayload()`).
     - A lista de papéis de negócio cadastrados para a organização (`Role`).
     - A descrição atualizada do processo + os comentários de melhoria do usuário.
   - O modelo de IA (Google Gemini / Azure OpenAI / Ollama / OpenRouter) reestrutura o grafo de etapas, atividades e transições.
   - **Extração e Auto-criação de Papéis**: Se o comentário de melhoria citar novos responsáveis/papéis que não existem na organização (ex.: "Compliance", "Diretoria Jurídica"), o serviço cria automaticamente esses papéis para a organização (`Role::create`) e os vincula às atividades geradas.

4. **Persistência Atômica**:
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

---

## 4. Atualização da Documentação e README

Este documento passa a integrar a suíte oficial de especificações técnicas do projeto em `docs/specs/06-refinamento-ia-editor.md`.
