## Escopo
- Modulo de produtos e estoque.
- Cobre `Product`, categorias, grupos, arquivos, inventario e relacoes do produto com outras entidades.

## Quando usar
- Prompts sobre produto, categoria, inventario, estoque, anexos de produto e estrutura de catalogo.

## Limites
- Regras de venda e pedido pertencem a `orders`.
- Regras de fila operacional de preparo pertencem a `queue`.
- Os metadados de grupo de produto (`priceCalculation`, `required`, `minimum`, `maximum`) e a quantidade/preco padrao de `product_group_product` formam o contrato de catalogo consumido pela tela de customizacao no frontend. Mudancas nesses campos precisam manter a leitura previsivel para `CustomizeScreen`.
