[English](../README.md) | **Português Brasileiro** | [Español](README-es.md)

# Relatório de títulos similares

Plugin **generic** para OJS que adiciona uma página em `Estatísticas` > `Títulos similares`, listando pares de submissões ativas cujos títulos são similares.

## Compatibilidade

Este plugin é compatível com OJS 3.5.0.x.

## Instalação

Para instalação em produção, utilize o pacote `.tar.gz` publicado na página de releases do plugin.

No OJS, acesse `Configurações` > `Website` > `Plugins` > `Enviar novo plugin`, selecione o pacote do plugin e confirme a instalação.

## Uso

Após habilitar o plugin, a página fica disponível em `Estatísticas` > `Títulos similares`.

### Acesso

Gerentes e administradores do site consultam as submissões ativas do contexto. Editores de seção consultam somente submissões nas quais possuem uma atribuição de estágio como editor de seção, seguindo o escopo de acesso do fluxo editorial do OJS.

### Critério de similaridade

A listagem compara títulos com `similar_text` e exibe pares com similaridade maior ou igual a 70%. O único pré-filtro compara o comprimento dos títulos e descarta apenas pares que matematicamente não podem atingir o limiar.

Para manter limites previsíveis de tempo e memória, o relatório compara no máximo as 200 submissões ativas acessíveis mais recentes e exibe no máximo os 1.000 pares com maior similaridade. A página apresenta um aviso quando qualquer um desses limites é atingido.

A similaridade calculada é guardada no cache da aplicação por 30 dias. A chave inclui os IDs das submissões e o `sha256` dos títulos normalizados; se um título mudar, a comparação é recalculada automaticamente.

## Desenvolvimento

Clone o plugin dentro de `plugins/generic/similarTitlesReport` em uma instalação local do OJS 3.5.

### Testes PHPUnit

Execute a partir da raiz do OJS:

```bash
TESTS=$(find -L plugins/generic/similarTitlesReport -name tests -type d -maxdepth 1) \
  && lib/pkp/lib/vendor/bin/phpunit --no-coverage --configuration lib/pkp/tests/phpunit.xml $TESTS
```

### PHP CS Fixer

Execute a partir da raiz do OJS antes de considerar alterações PHP prontas:

```bash
php lib/pkp/lib/vendor/bin/php-cs-fixer fix \
  --config .php-cs-fixer.php --allow-risky=yes \
  plugins/generic/similarTitlesReport/
```

### Testes Cypress

Execute a partir da raiz do OJS:

```bash
npx cypress run \
  --config 'baseUrl=http://localhost:8000,specPattern=plugins/generic/similarTitlesReport/cypress/tests/**/*.cy.js' \
  --browser chrome
```

Os testes Cypress do plugin não são idempotentes. Rode a suíte completa contra uma base de dados fresca, na ordem dos specs existentes.

## Licença

Este plugin é licenciado sob a GNU General Public License v3.0.

_Copyright (c) 2026 Lepidus Tecnologia_
