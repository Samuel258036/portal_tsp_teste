<?php
declare(strict_types=1);
session_start();

const APP_NAME = 'TSP Advogados — Área do Cliente';
const DEMO_CPF = '12345678900';
const DEMO_PASSWORD = 'demonstracao';

function only_digits(string $value): string {
    return preg_replace('/\D+/', '', $value) ?? '';
}
function logged_in(): bool {
    return !empty($_SESSION['cliente']);
}
function require_login(): void {
    if (!logged_in()) {
        header('Location: index.php');
        exit;
    }
}
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$cliente = [
    'nome' => 'Cliente Demonstração',
    'cpf' => '123.456.789-00',
    'email' => 'cliente@exemplo.com',
];

$processos = [
    [
        'numero' => '1023456-78.2025.8.26.0554',
        'titulo' => 'Processo demonstrativo',
        'tribunal' => 'TJSP',
        'vara' => '2ª Vara Cível',
        'status' => 'Aguardando audiência',
        'proximo' => 'Audiência de conciliação',
        'data' => '18/11/2026 às 14:30',
        'fase' => 3,
        'fases' => ['Petição inicial', 'Citação', 'Defesa', 'Audiência', 'Sentença'],
        'resumo' => 'O processo segue normalmente. A próxima etapa prevista é a audiência de conciliação. No momento, não há nenhuma ação necessária por parte do cliente.'
    ],
    [
        'numero' => '1009876-12.2026.8.26.0100',
        'titulo' => 'Acompanhamento contratual',
        'tribunal' => 'TJSP',
        'vara' => '10ª Vara Cível',
        'status' => 'Em análise',
        'proximo' => 'Aguardar manifestação',
        'data' => 'Sem data definida',
        'fase' => 2,
        'fases' => ['Distribuição', 'Análise', 'Manifestação', 'Decisão'],
        'resumo' => 'A equipe está acompanhando a análise do processo. Assim que houver nova movimentação relevante, ela será apresentada nesta área.'
    ]
];

$parcelas = [
    ['numero'=>1, 'vencimento'=>'10/08/2026', 'valor'=>'R$ 750,00', 'status'=>'Paga'],
    ['numero'=>2, 'vencimento'=>'10/09/2026', 'valor'=>'R$ 750,00', 'status'=>'Paga'],
    ['numero'=>3, 'vencimento'=>'10/10/2026', 'valor'=>'R$ 750,00', 'status'=>'Em aberto'],
    ['numero'=>4, 'vencimento'=>'10/11/2026', 'valor'=>'R$ 750,00', 'status'=>'Em aberto'],
];

if (!isset($_SESSION['chamados'])) {
    $_SESSION['chamados'] = [
        ['id'=>'TSP-1042','assunto'=>'Dúvida sobre audiência','categoria'=>'Processo','status'=>'Respondido','data'=>'02/10/2026',
         'mensagens'=>[
            ['autor'=>'Você','texto'=>'Gostaria de confirmar se preciso levar algum documento adicional.'],
            ['autor'=>'Equipe TSP','texto'=>'Olá! Para esta audiência, leve documento oficial com foto. Se houver alguma orientação adicional, avisaremos por aqui.']
         ]]
    ];
}
?>