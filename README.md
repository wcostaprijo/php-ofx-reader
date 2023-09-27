PHP Ofx Reader
===

**PHP Ofx Reader** é uma biblioteca simples para leitura de transações em arquivos OFX extraídos das instituições financeiras.

A maior diferença da biblioteca é a utilização de templates para leitura dos arquivos, dessa forma você consegue ler OFX de qualquer instituição financeira.

## Instalação

A instalação é simples bastar usar o [Composer](https://getcomposer.org/):

```bash
composer require wc-develop/php-ofx-reader
```

## Uso

#### Template Generico
A biblioteca possuí um template generico que atende a maior parte dos arquivos OFX oferecidos no mercado, para usa-lo siga os passos abaixo:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use WcDeveloper\PhpOfxReader\OfxReader;

$path = '/var/www/html/arquivo.ofx';
$ofxReader = new OfxReader($path);
$transactions = $ofxReader->getTransactions();
```

#### Template Personalizado
Criar seu template é muito simples, basta seguir os passos abaixo:

1° Crie a classe do template: (*O template pode ser criado em qualquer parte do seu projeto*)

```php
<?php

namespace Seu\NameSpace;

use WcDeveloper\PhpOfxReader\TemplateInterface;

class CustomTemplate implements TemplateInterface
{
    // Informe a tag root do arquivo, na maioria dos casos será ofx
    public function rootTag() : string
    {
        return 'ofx';
    }

    // informe o caminho completo dentro do seu arquivo para chegar até as transações
    // separe com ->
    public function mapTransactions() : string
    {
        return 'BANKMSGSRSV1->STMTTRNRS->STMTRS->BANKTRANLIST->STMTTRN';
    }

    /**
     * Mapeie as transações do seu arquivo OFX
     * Tipo de dados disponíveis:
     *  - money
     *  - date
     *  - string
     * 
     * Estrutura de retorno da array:
     * [
     *  'qualquer-key' => ['key' => 'tag-dentro-do-arquivo-ofx', 'type' => 'tipo-do-dado'],
     *  'key-qualquer' => ['key' => 'tag-dentro-do-arquivo-ofx', 'type' => 'tipo-do-dado'],
     * ]
     *
     * @return array
     */
    public function mapTransaction() : array
    {
        return [
            'valor' => ['key' => 'TRNAMT', 'type' => 'money'],
            'identificacao'=> ['key' => 'CHECKNUM', 'type' => 'string'],
            'historico' => ['key' => 'MEMO', 'type' => 'string'],
            'data_extrato' => ['key' => 'DTPOSTED', 'type' => 'date'],
        ];
    }
}

```

2° Basta usar seu template personalizado:
```php
<?php

require __DIR__ . '/vendor/autoload.php';

use WcDeveloper\PhpOfxReader\OfxReader;
use Seu\NameSpace\CustomTemplate;

$url = 'https://seu-dominio.com/arquivo.ofx';
$ofxReader = new OfxReader($url, CustomTemplate::class);
$transactions = $ofxReader->getTransactions();
```

Inicialmente essa biblioteca apenas lê as transações, em breve outras informações serão adicionadas.