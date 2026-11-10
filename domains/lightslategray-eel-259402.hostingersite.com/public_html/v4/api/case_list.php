// 1. Inclua o helper de acesso
require_once __DIR__ . '/access_control.php';

$user = current_user();
$userId = (int)$user['id'];

// 2. Obtenha a cláusula WHERE e os parâmetros corretos
$queryData = get_authorized_cases_query($pdo, $userId);

// 3. Utilize na sua listagem
$sql = "
    SELECT c.id, c.case_number, c.title, c.status 
    FROM cases c 
    WHERE {$queryData['where']} 
    ORDER BY c.updated_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($queryData['params']);
$meusProcessos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ... Renderize a tabela com $meusProcessos ...