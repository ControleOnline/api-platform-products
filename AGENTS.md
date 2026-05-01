## Escopo
- Modulo de produtos e estoque.
- Cobre `Product`, categorias, grupos, arquivos, inventario e relacoes do produto com outras entidades.

## Quando usar
- Prompts sobre produto, categoria, inventario, estoque, anexos de produto e estrutura de catalogo.

## Limites
- Regras de venda e pedido pertencem a `orders`.
- Regras de fila operacional de preparo pertencem a `queue`.
- Os metadados de grupo de produto (`priceCalculation`, `required`, `minimum`, `maximum`) e a quantidade/preco padrao de `product_group_product` formam o contrato de catalogo consumido pela tela de customizacao no frontend. Mudancas nesses campos precisam manter a leitura previsivel para `CustomizeScreen`.
- `ProductService` e o dono da fronteira de autorizacao do catalogo. `securityFilter()` deve limitar leitura de `Product` as empresas realmente acessiveis ao ator autenticado, incluindo a propria empresa quando o usuario opera o catalogo da pessoa dona.
- Criacao, edicao, exclusao e importacao CSV de `Product` exigem acesso administrativo de catalogo para a empresa alvo. `ROLE_HUMAN` isolado nao basta para gravar no catalogo.
- Endpoints derivados de leitura, como inventario, sugestao de compra e busca por SKU, devem reutilizar a mesma regra de visibilidade por empresa e falhar fechado quando a empresa solicitada nao estiver no escopo acessivel.
- Quando `Product.type = service`, a persistencia deve aceitar apenas unidades de cobranca compativeis com execucao unica ou recorrencia. Medidas fisicas como litro, grama e fracao nao podem ser salvas para servicos mesmo se o payload contornar o frontend.
