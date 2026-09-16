<?php
declare(strict_types=1);
require dirname(__DIR__) . '/installation.php';
$cases = [
    ['<script src="https://metrika.clickshot.ru/counter.js?id=abc"></script>', true],
    ['<!-- <script src="https://metrika.clickshot.ru/counter.js?id=abc"></script> -->', false],
    ['<script src="https://metrika.clickshot.ru/counter.js?id=abc-other"></script>', false],
    ['<script src="https://other.example/counter.js?id=abc"></script>', false],
    ['<script src="//metrika.clickshot.ru/counter.js?id=abc&amp;v=1"></script>', true],
];
foreach ($cases as [$html,$expected]) {
    if (inspect_installation($html,'abc','metrika.clickshot.ru')['installed'] !== $expected) throw new RuntimeException('Installation matching failed');
}
$result=inspect_installation('<script async src="https://metrika.clickshot.ru/counter.js?id=abc"></script><script src="https://mc.yandex.ru/metrika/tag.js"></script>','abc','metrika.clickshot.ru');
if ($result['warnings'] !== ['asynchronous_counter','static_metrika_script']) throw new RuntimeException('Missing warnings');
echo "Installation matching passed\n";
