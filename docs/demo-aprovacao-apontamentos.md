# Demo: Aprovação de Apontamentos

Processo de demonstração com dois pontos de decisão (Gestor e RH podem reprovar), pensado para
validar tanto o caminho feliz quanto o de reprovação de uma instância via "Iniciar", do início ao
fim.

## Campo Descrição do Workflow

> Preciso de um fluxo de aprovação de apontamento de horas, com reprovação. Funciona assim: o
> Colaborador preenche o apontamento do período dele. Esse apontamento vai pro Gestor direto, que
> confere se as horas batem com o que foi realmente trabalhado — se estiver certo ele aprova e
> segue pro RH, se tiver algo errado ele reprova e o processo já termina reprovado ali mesmo, sem
> voltar pro colaborador. Quando chega no RH, ele faz uma checagem final: se aprovar, libera o
> apontamento pro fechamento da folha; se reprovar, o processo também termina, só que reprovado
> pelo RH. Ou seja, dois pontos de decisão — Gestor e RH — cada um podendo aprovar ou reprovar, e
> quero o fluxo o mais enxuto possível mesmo com essas duas decisões, sem etapa extra.

**Nome sugerido:** `Aprovação de Apontamentos`

## Estrutura (4 etapas, 6 atividades)

| Etapa | Atividade | Tipo | Responsável | Flags |
|---|---|---|---|---|
| 1. Lançamento | Preencher apontamento | `task` | role `Colaborador` | `is_start = true` |
| 2. Aprovação do Gestor | Aprovar apontamento | `task` | role `Gestor` | — |
| 3. Validação do RH | Validar apontamento | `task` | role `RH` | — |
| 4. Resultado | Apontamento aprovado | `task` | — | `is_end = true` |
| 4. Resultado | Apontamento reprovado pelo Gestor | `task` | — | `is_end = true` |
| 4. Resultado | Apontamento reprovado pelo RH | `task` | — | `is_end = true` |

**Transições:**

- Preencher apontamento → Aprovar apontamento — `always` (rótulo opcional: "Enviado para
  aprovação")
- Aprovar apontamento → Validar apontamento — `expression`, `result.decision = "aprovado"`
  (rótulo: "Aprovado pelo Gestor")
- Aprovar apontamento → Apontamento reprovado pelo Gestor — `expression`,
  `result.decision = "reprovado"` (rótulo: "Reprovado pelo Gestor")
- Validar apontamento → Apontamento aprovado — `expression`, `result.decision = "aprovado"`
  (rótulo: "Aprovado pelo RH")
- Validar apontamento → Apontamento reprovado pelo RH — `expression`,
  `result.decision = "reprovado"` (rótulo: "Reprovado pelo RH")

Cada um dos três finais (`Apontamento aprovado`, `Apontamento reprovado pelo Gestor`, `Apontamento
reprovado pelo RH`) tem exatamente um predecessor — não reconvergem no mesmo nó, para não formar
um join implícito com caminhos mutuamente exclusivos (ver `WorkflowGraphValidator` /
`docs/specs/02-motor-de-execucao.md` §3.3.1).

## Roteiro de teste

1. Criar os roles `Colaborador`, `Gestor` e `RH` na organização (se ainda não existirem) e associar
   o usuário de teste a todos, para conseguir assumir cada atividade sozinho.
2. Montar as 4 etapas/6 atividades/5 transições acima no editor e publicar a versão.
3. Caminho feliz: clicar em "Iniciar", nomear a instância (ex.: `Teste — Apontamento Agosto/2026`),
   aprovar em "Aprovar apontamento" e em "Validar apontamento", e conferir que o status vira
   `completed` em "Apontamento aprovado".
4. Caminho de reprovação pelo Gestor: nova instância, reprovar em "Aprovar apontamento" e conferir
   que termina direto em "Apontamento reprovado pelo Gestor", sem passar pelo RH.
5. Caminho de reprovação pelo RH: nova instância, aprovar em "Aprovar apontamento" e reprovar em
   "Validar apontamento", conferindo que termina em "Apontamento reprovado pelo RH".
