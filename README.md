# Relatório de títulos similares

Plugin genérico para OJS 3.5 que lista pares de submissões ativas com títulos similares.

O relatório fica disponível em `Estatísticas > Títulos similares` para gerentes, administradores do site e editores de seção. Gerentes e administradores consultam todas as submissões ativas do contexto; editores de seção ficam restritos às seções associadas ao usuário.

## Critério

A listagem compara títulos com `similar_text` e exibe pares com similaridade maior ou igual a 70%. Para evitar timeout em bases maiores, o plugin aplica pré-filtros por tamanho e termos aproximados antes do cálculo final, mas a porcentagem exibida é sempre a retornada por `similar_text`.

A similaridade calculada é guardada no cache da aplicação por 30 dias. A chave inclui os IDs das submissões e o `sha256` dos títulos normalizados; se um título mudar, a comparação é recalculada automaticamente.

## Testes locais

Sincronize a pasta do plugin para uma instalação OJS 3.5 e execute:

```bash
find plugins/generic/similarTitlesReport -name '*.php' -print0 | xargs -0 -n1 php -l
lib/pkp/lib/vendor/bin/phpunit --no-coverage -c lib/pkp/tests/phpunit.xml plugins/generic/similarTitlesReport/tests/PublicationTitleFormatterTest.php plugins/generic/similarTitlesReport/tests/SimilarTitlePairFinderTest.php plugins/generic/similarTitlesReport/tests/SimilarTitlesDataProviderTest.php
```
