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
2. [x] **Fase 1 — Estrutura**: banco de dados, autenticação real e sessões
   (`AuthController`, senhas com `password_hash`/`password_verify`, guardas de rota).
3. [x] **Fase 2 — Módulo Viagens**: CRUD e status (`aberta`/`encerrada`).
4. [~] **Fase 3 — Gastos e Compras**: CRUD vinculados à viagem — **entregue**;
   pendente apenas a revisão de relatórios. Gastos e Compras contam com leitura
   de recibo/nota por IA (Gemini) em campo único ou em lote (bloco de itens).
5. [ ] **Fase 4 — Vendas e estoque**: baixa de estoque e resultados.
6. [ ] **Fase 5 — Relatórios e painel geral**: KPIs reais e gráficos consolidados.
7. [ ] **Fase 6 — Finalização**: validações, segurança e deploy.

## Módulos entregues

| Módulo  | Status | Observações |
|---------|--------|-------------|
| Login   | ✅ | Login/logout, sessões, último acesso |
| Viagens | ✅ | CRUD, status aberta/encerrada, orçamento |
| Gastos  | ✅ | CRUD por categoria; IA lê recibo/nota (campo único + conciliação) |
| Compras | ✅ | CRUD de itens; **IA lê a nota e lança em bloco (item a item)** |
| Painel  | ✅ | KPIs reais (total gasto, total comprado, viagens) |

## Módulo Compras (detalhes)

- Registro item a item por viagem: produto, marca, categoria
  digitáveis com sugestões (novas marcas/categorias passam a ser sugeridas
  nos lançamentos seguintes), quantidade, valor, loja, data, moeda e cotação
  (dólar com cotação automática BCB/PTAX).
- Galeria de fotos por item (`compra_fotos`, upload JPG/PNG/WEBP até 2 MB,
  armazenadas em `public/uploads/compras/`, protegidas por `.gitignore`).
- Leitura por IA (`LeituraReceita::lerItens` + `?modo=itens`): retorna
  `{loja, moeda, data, itens[]}` e o formulário do bloco é pré-preenchido.
- Tabelas: `compras` (valor/cotação unitários + `valor_brl` calculado) e
  `compra_fotos`. Somas por categoria/total usam `valor_brl * quantidade`.

## Próximos passos

- Módulo Vendas com baixa de estoque e custo por peça.
- KPIs de lucro e gráficos reais no painel (hoje ilustrativos).