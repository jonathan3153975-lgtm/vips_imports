# Plano — Sistema de Controle de Viagens, Compras e Vendas

## Sinopse

Sistema web para controle financeiro de viagens de compras e vendas. O usuário
registra a viagem, os gastos realizados durante a viagem, os itens comprados e,
posteriormente, as vendas desses itens. O sistema consolida o resultado
financeiro de cada viagem e também um painel geral com gastos e retornos
(por período, por viagem, por produto).

## Objetivos

- Registrar viagens (destino, período, orçamento).
- Registrar gastos da viagem (diárias, transporte, alimentação, etc.).
- Registrar compras de itens (produto, quantidade, custo unitário, fornecedor).
- Registrar vendas (item, quantidade, preço, cliente, data).
- Controlar estoque por viagem (comprado x vendido x pendente).
- Apresentar resultado financeiro por viagem.
- Painel geral com indicadores de gastos e retornos.
- Perfil de usuário e segurança (login).

## Público-alvo

Viajantes e pequenos lojistas que fazem compras fora do país/estado e revendem
os itens após o retorno.

## Funcionalidades previstas

| Módulo      | Descrição |
|-------------|-----------|
| Login       | Autenticação de usuário, recuperação de senha, modo noturno |
| Painel      | KPIs: total gasto, total vendido, lucro, viagens ativas; gráficos |
| Viagens     | CRUD de viagens; status (planejada, ativa, concluída) |
| Gastos      | CRUD de gastos vinculados à viagem, por categoria |
| Compras     | CRUD de itens comprados, com custo e quantidade |
| Vendas      | CRUD de vendas, baixa automática de estoque |
| Relatórios  | Resultado por viagem; lucro por produto; projeções |

## Regras de negócio (resumo)

- Uma viagem possui zero ou muitos gastos e itens comprados.
- Um item comprado pode gerar uma ou várias vendas.
- O custo por item vendido = custo da compra distribuído peça a peça.
- Resultado da viagem = total vendido − (total comprado + total gasto).
- Lucro disponível é calculado após todas as vendas registradas.

## Modelo de dados (versão inicial)

Entidades principais: `usuarios`, `viagens`, `gastos`, `itens`, `vendas`
(e `itens_vendidos` para a baixa por venda).

## Stack técnica

- **Backend**: PHP 8 (padrão MVC já esboçado em `app/`, conexão PDO em
  `database/conexao.php`, credenciais em `.env`).
- **Frontend**: Bootstrap 5.3.3, jQuery 3.7.1, Font Awesome 6.5.2,
  Chart.js 4.5.1 (em `public/assets`).
- **Banco**: MySQL (SGC_DEV).
- **Design**: tons de azul, vermelho e branco; modo claro e noturno;
  responsivo com barra de ações no rodapé no smartphone.

## Etapas de implementação

1. [x] **Fase 0 — Protótipo visual**: documento `plano.md`, login e painel.
2. [ ] **Fase 1 — Estrutura**: banco de dados, autenticação real, sessões.
3. [ ] **Fase 2 — Módulo Viagens**: CRUD e status.
4. [ ] **Fase 3 — Gastos e Compras**: CRUD vinculados à viagem.
5. [ ] **Fase 4 — Vendas e estoque**: baixa de estoque e resultados.
6. [ ] **Fase 5 — Relatórios e painel geral**: indicadores e gráficos.
7. [ ] **Fase 6 — Finalização**: validações, segurança e deploy.