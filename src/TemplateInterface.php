<?php

namespace WcDeveloper\PhpOfxReader;

interface TemplateInterface
{
    public function rootTag(): string;

    public function mapTransactions(): string;

    public function mapTransaction(): array;
}
