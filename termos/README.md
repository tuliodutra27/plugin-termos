# Plugin Termos — GLPI

Plugin para geração de **termos de responsabilidade** em PDF para ativos cadastrados no GLPI (monitores, desktops, telefones, rádios).

## Requisitos

- GLPI 10.0.0 ou superior
- PHP 8.0+
- Biblioteca TCPDF (incluída no GLPI via Composer em `vendor/tecnickcom/tcpdf/`)
- Fonte **Arial** instalada no TCPDF (ver seção abaixo)

## Instalação

### 1. Copiar o plugin

Copie a pasta `termos/` para o diretório de plugins do GLPI:

```
/var/www/glpi/plugins/termos/
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

Acesse **Configuração → Plugins**, localize **termos** e clique em **Instalar**, depois em **Ativar**.

## Tabelas criadas

| Tabela | Descrição |
|---|---|
| `glpi_termos_cabecalho` | Cabeçalho do documento (logo, títulos, versão, setor, revisão) |
| `glpi_plugin_termo_clausulas` | Cláusulas do termo (índice + texto) |
| `glpi_plugin_termo_observacoes` | Observações do termo (antes ou após as cláusulas) |

## Funcionalidades

### Gerar Termo

Selecione um item cadastrado no GLPI (monitor, desktop, telefone, rádio) e gere o PDF do termo de responsabilidade. O sistema lê campos customizados armazenados no campo **Comentário** do item usando as tags abaixo.

**Tags suportadas no campo Comentário do ativo:**

| Tag | Descrição |
|---|---|
| `[cpf]...[/cpf]` | CPF do responsável |
| `[cargo]...[/cargo]` | Cargo do responsável |
| `[acessorios]...[/acessorios]` | Acessórios incluídos |
| `[valor]...[/valor]` | Valor do equipamento |
| `[prefixo]...[/prefixo]` | Prefixo (rádios) |
| `[operadora]...[/operadora]` | Operadora (telefones) |
| `[tipo]...[/tipo]` | Tipo do equipamento |
| `[numero_chamado]...[/numero_chamado]` | Número do chamado |
| `[nome]...[/nome]` | Nome do responsável |
| `[serial_equip]...[/serial_equip]` | Serial do equipamento |

### Configurar Cabeçalho

Define os dados do cabeçalho do PDF: logo, título principal, título secundário, versão/série, setor, revisão, número de páginas e data da versão.

### Gerenciar Cláusulas

CRUD de cláusulas do termo. Cada cláusula possui um índice (ex.: `1.`, `1.1`, `2.`) e o texto correspondente. As cláusulas são inseridas no corpo do PDF na ordem do índice.

### Gerenciar Observações

CRUD de observações. Semelhante às cláusulas, com controle de posição no documento (antes ou após as cláusulas).

## Desinstalação

Acesse **Configuração → Plugins**, localize **termos**, clique em **Desativar** e depois em **Desinstalar**.

> A desinstalação remove permanentemente as tabelas `glpi_termos_cabecalho`, `glpi_plugin_termo_clausulas` e `glpi_plugin_termo_observacoes`.

## Autores

Diego, Luciano, Rafael e Tulio — ALISEO SA
