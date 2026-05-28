# Plugin Termos — GLPI

Plugin para geração de **termos de responsabilidade** em PDF para ativos cadastrados no GLPI.

Ativos suportados: **Computadores, Monitores, Telefones, Linhas Telefônicas, Rádios, Impressoras e Periféricos**.

> Este repositório também inclui o plugin **Radios** na pasta [`radios/`](radios/).

## Requisitos

- GLPI 10.0.0 ou superior
- PHP 8.0+
- Biblioteca TCPDF (incluída no GLPI via Composer em `vendor/tecnickcom/tcpdf/`)
- Fonte **Arial** instalada no TCPDF (ver seção abaixo)

## Instalação

### 1. Copiar os plugins

Clone o repositório ou baixe o ZIP e copie as pastas para o diretório de plugins do GLPI:

```bash
# Plugin Termos — copie a raiz deste repositório
cp -r plugin-termos/  /var/www/glpi/plugins/termos/

# Plugin Radios — copie a subpasta radios/
cp -r plugin-termos/radios/  /var/www/glpi/plugins/radios/
```

### 2. Instalar a fonte Arial no TCPDF

O plugin usa a fonte Arial para manter a formatação padrão dos documentos. Copie os três arquivos de fonte para o diretório do TCPDF:

```
arial.php
arial.z
arial.ctg.z
```

Destino:
```
/var/www/glpi/vendor/tecnickcom/tcpdf/fonts/
```

> Os arquivos de fonte podem ser gerados com o utilitário `tcpdf_addfont` ou obtidos de uma instalação já configurada.

### 3. Ativar no GLPI

Acesse **Configuração → Plugins**, localize **termos** (e **Radios** se desejar) e clique em **Instalar**, depois em **Ativar**.

## Tabelas criadas

| Tabela | Descrição |
|---|---|
| `glpi_termos_cabecalho` | Cabeçalho do documento (logo, títulos, versão, setor, revisão) |
| `glpi_plugin_termo_clausulas` | Cláusulas do termo (índice + texto) |
| `glpi_plugin_termo_observacoes` | Observações do termo (antes ou após as cláusulas) |

## Funcionalidades

### Gerar Termo

Selecione um usuário e gere o PDF do termo de responsabilidade. O sistema busca automaticamente todos os ativos vinculados ao usuário: computadores, monitores, telefones, linhas, rádios, impressoras e periféricos.

Campos customizados são lidos do campo **Comentário** de cada ativo via tags:

| Tag | Descrição |
|---|---|
| `[cpf]...[/cpf]` | CPF do responsável (no usuário) |
| `[cargo]...[/cargo]` | Cargo do responsável (no usuário) |
| `[acessorios]...[/acessorios]` | Acessórios incluídos (no ativo) |
| `[valor]...[/valor]` | Valor do equipamento (no ativo) |
| `[prefixo]...[/prefixo]` | Prefixo (linhas telefônicas) |
| `[operadora]...[/operadora]` | Operadora (linhas telefônicas) |
| `[tipo]...[/tipo]` | Tipo do equipamento (linhas) |
| `[numero_chamado]...[/numero_chamado]` | Número do chamado (linhas) |
| `[nome]...[/nome]` | Nome do responsável (linhas) |
| `[serial_equip]...[/serial_equip]` | Serial do equipamento (linhas) |

### Configurar Cabeçalho

Define os dados do cabeçalho do PDF: logo, título principal, título secundário, versão/série, setor, revisão, número de páginas e data da versão.

### Gerenciar Cláusulas

CRUD de cláusulas do termo. Cada cláusula possui um índice (ex.: `1.`, `1.1`, `2.`) e o texto correspondente.

### Gerenciar Observações

CRUD de observações com controle de posição no documento (antes ou após as cláusulas).

## Desinstalação

Acesse **Configuração → Plugins**, localize **termos**, clique em **Desativar** e depois em **Desinstalar**.

> A desinstalação remove permanentemente as tabelas `glpi_termos_cabecalho`, `glpi_plugin_termo_clausulas` e `glpi_plugin_termo_observacoes`.

## Autores

Diego, Luciano, Rafael e Tulio — ALISEO SA
