## Escopo
- Modulo de produtos e estoque.
- Cobre `Product`, categorias, grupos, arquivos, inventario e relacoes do produto com outras entidades.

## Quando usar
- Prompts sobre produto, categoria, inventario, estoque, anexos de produto e estrutura de catalogo.

## Limites
- Regras de venda e pedido pertencem a `orders`.
- Regras de fila operacional de preparo pertencem a `queue`.
- Os metadados de grupo de produto (`priceCalculation`, `required`, `minimum`, `maximum`) e a quantidade/preco padrao de `product_group_product` formam o contrato de catalogo consumido pela tela de customizacao no frontend. Mudancas nesses campos precisam manter a leitura previsivel para `CustomizeScreen`.

## Regras de seguranca e autorizacao
- Entidade analisada: `Product`.
- Service correspondente: `src/Service/ProductService.php`.
- `ProductService::securityFilter()` e obrigatorio e precisa aplicar filtro real de leitura e escrita. Metodo vazio, comentado ou apenas nominal nao conta como protecao valida.
- `Product` nao deve depender apenas de `Get` ou `GetCollection` com `PUBLIC_ACCESS`, nem de `Put`/`Delete` guardados so por `ROLE_HUMAN`, para expor ou alterar catalogo de empresa.
- Leitura de `Product` deve ficar restrita ao contexto de empresa realmente acessivel ao ator autenticado ou a regra administrativa equivalente explicitamente comprovada.
- Criacao, edicao e exclusao de `Product` devem exigir autorizacao explicita para gerir catalogo/estoque da empresa alvo; nao basta estar autenticado nem informar `company` arbitraria no payload.
- Entidade analisada: `ProductPeople`.
- Service correspondente: `src/Service/ProductPeopleService.php` ou camada equivalente que proteja a relacao.
- Toda criacao, edicao e exclusao de `ProductPeople` precisa validar ao mesmo tempo o direito de gerir o `Product` alvo e o direito de vincular a `People` alvo como fornecedor/fabricante/distribuidor. `ROLE_HUMAN` isolado nao e protecao suficiente.
- Quando o frontend abrir criacao de produto contextualizada por fornecedor, o primeiro salvamento so pode materializar a relacao `supplier` se o backend reaplicar essa fronteira por identidade autenticada. Nao confiar em `people` ou `product` enviados pelo cliente como prova de autorizacao.
- Para `type=service`, a API precisa rejeitar em persistencia e atualizacao unidades fisicas incompativeis e aceitar apenas unidades de cobranca coerentes com execucao unica ou recorrencia.
- Em edicao de legado, manter a unidade antiga visivel no frontend pode ser aceitavel para preservar contexto, mas a persistencia de novo valor invalido continua proibida no backend.
