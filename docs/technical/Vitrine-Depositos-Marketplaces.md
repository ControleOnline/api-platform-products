# Vitrine ↔ Depósitos (marketplaces e estoque por canal)

## Problema

Na ficha do produto (`product-details/{id}/Estoque`) o saldo é **por depósito** (ex.: Geladeiras, Freezer, Casa).

Nos marketplaces (Mercado Livre, iFood, shop próprio, etc.) o **mesmo anúncio** pode vender a partir de **vários pontos**:

| Origem física / lógica | Exemplo |
|------------------------|---------|
| Full / fulfillment do canal | 2 un. no Full ML |
| Casa | 4 un. em casa |
| Depósito próprio | 10 un. no depósito |

Tudo isso pode estar **disponível para venda no canal**, mas no ERP continua separado por depósito.

## Modelo no Controle Online

- **Marketplace / canal de venda** = **Vitrine** (`ProductShowcase`)
  - Campo `integrationKey`: `mercadolivre`, `ifood`, `shop`, `99food`, …
  - `name`: rótulo amigável (ex.: "ML Full", "ML Padrão", "Loja site")
- **Depósito** = `Inventory` (ex.: Geladeiras, Freezer, Casa, Depósito)
- **Saldo do produto no depósito** = `ProductInventory` (disponível, mín, máx, …)

### Associação nova

**N:N** `ProductShowcase` ↔ `Inventory` (tabela `product_showcase_inventory`).

Uma vitrine declara **quais depósitos alimentam a disponibilidade daquele canal**.

```
Vitrine "ML Full"      (integrationKey=mercadolivre)  →  [Depósito Full]
Vitrine "ML Padrão"    (integrationKey=mercadolivre)  →  [Casa, Depósito]
Vitrine "Shop"         (integrationKey=shop)          →  [Casa, Depósito]
Vitrine "iFood"        (integrationKey=ifood)           →  [Geladeiras]
```

### Disponibilidade por canal (regra)

Para um produto P e vitrine V:

```
disponivel(V, P) = soma de ProductInventory.available
                   nos depósitos vinculados a V
                   (mesma company; só vínculos ativos)
```

O sync de estoque para o marketplace (ex.: `pushItemStock` no ML) deve usar **essa soma** (ou o depósito explícito configurado na vitrine), não um único depósito arbitrário.

## UX — aba Estoque do produto

Por card de depósito:

1. Nome do depósito + métricas atuais (disponível, vendas, pedidos, …)
2. **Ícones / chips das vitrines** cujo N:N inclui aquele depósito
   - Ícone ou badge por `integrationKey` (ML, iFood, shop, …)
   - Tooltip com o **nome** da vitrine ("ML Full" vs "ML Padrão")

Assim o operador vê, no mesmo lugar do print (`Geladeiras` / `Freezer`), **quais canais vendem a partir daquele depósito**.

## Configuração sugerida (exemplo owner)

1. Criar depósitos: `Full`, `Casa`, `Depósito` (se ainda não existirem).
2. Criar vitrine **ML Full** (`integrationKey=mercadolivre`) → vincular só `Full`.
3. Criar vitrine **ML Padrão** (`integrationKey=mercadolivre`) → vincular `Casa` + `Depósito`.
4. Na importação/anúncio, escolher a vitrine correta; o estoque empurrado ao ML reflete a soma dos depósitos da vitrine.

## API (leitura/escrita)

- `ProductShowcase.inventories` — coleção de `/inventories/{id}` (groups `product_showcase:read|write`)
- `Inventory.showcases` — leitura das vitrines que usam o depósito (`inventory:read`)

Escrita: PUT/PATCH da vitrine com lista de inventories **da mesma company**.

## Segurança

- Associação só entre vitrine e depósito da **mesma empresa**
- Operações com `ROLE_HUMAN` e filtros de company já usados em products/showcase

## Issue

ControleOnline/app-community#1251
