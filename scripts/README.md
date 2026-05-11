# Scripts de Inserção de Dados

## Como Usar

### Método 1: Via Painel WordPress (Recomendado)

1. Acesse o arquivo `functions.php` do tema
2. Descomente a linha que carrega o script:
   ```php
   require_once get_template_directory() . '/scripts/insert-data.php';
   ```
3. Salve o arquivo
4. Acesse qualquer página do site (isso executará o script automaticamente)
5. Volte ao `functions.php` e comente a linha novamente

### Método 2: Via URL Admin

1. Acesse: `http://seusite.com/wp-admin/?tutorial_insert_data=1`
2. Os dados serão inseridos automaticamente

### Método 3: Via WP-CLI

```bash
wp eval-file scripts/insert-data.php
```

## O que o Script Faz

1. **Cria Veículos** (como termos da taxonomia):
   - Sedan Standard
   - Sedan Premium
   - Van Standard
   - Van Premium

2. **Cria Serviços**:
   - Transfer (com preços por veículo)
   - Tours Privados (sob consulta)
   - Motorista por Hora (sob consulta)
   - Eventos Especiais (sob consulta)

3. **Cria Destinos** (exemplo):
   - Sintra
   - Fátima
   - Cascais

## Notas Importantes

- O script verifica se os dados já existem antes de criar
- Pode ser executado múltiplas vezes sem duplicar dados
- Após a primeira execução, marque como executado para evitar reprocessamento
