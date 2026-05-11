# WordPress — módulos de tema (excertos)

Pastas com **funcionalidades isoladas** de um tema WordPress customizado: hooks, configs, CRUD, SEO, templates, scripts de manutenção, geo API, etc. **Não** é o tema completo nem um `wp-content` inteiro — é uma forma de mostrar **como o código foi modularizado** para portfólio e revisão.

## Mapa rápido

| Pasta | Exemplos |
|-------|----------|
| `configs/` | Arranque do tema, scripts, filtros, cache |
| `functions/` | Lógica de negócio (ex.: pagamentos Stripe) |
| `template-parts/` | Partials, formulários, modais, anúncios |
| `crud/` | Operações sobre custom post types / anúncios |
| `scripts/` | Ferramentas admin/CLI (normalização, blog, dados) |
| `geo-api/` | CEP / localização |
| `seo/` | Meta, schema, Pinterest |

## Como “mostrar” isto num emprego

1. Descreva no README do **repositório pinado** o papel de cada bloco (1–2 frases por pasta relevante).
2. Indique que **credenciais e domínios** foram removidos ou substituídos por placeholders.
3. Se tiver **demo** ou screenshots, ligue aqui.

## Instalação

Estes ficheiros pressupõem um **WordPress** com tema activo e dependências (ACF, rotas, etc.) configuradas no projecto original. Para um leitor externo, o valor está na **leitura do código** e na **estrutura**, não num `wp core download` isolado.

## Topics sugeridos

`wordpress` `php` `theme-development` `acf` `seo` `portfolio`
