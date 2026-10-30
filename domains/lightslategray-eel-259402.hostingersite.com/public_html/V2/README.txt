TSP PORTAL V2 — HOMOLOGAÇÃO PHP
4 perfis: Admin, Gestão TSP, Adv TSP e Cliente.
Esta versão usa SESSION para permitir testar cadastros sem banco. Dados reiniciam ao encerrar/limpar a sessão.

LOGIN:
Admin: admin@tsp.demo / admin123
Gestão: gestao@tsp.demo / gestao123
Adv: adv@tsp.demo / adv123
Cliente: cliente@demo.com / cliente123

INSTALAÇÃO:
1. No site temporário, crie public_html/V2 (ou outra pasta).
2. Envie este ZIP para essa pasta e extraia.
3. Garanta que index.php esteja diretamente dentro da pasta V2.
4. Acesse /V2/ no domínio temporário.

IMPORTANTE: Homologação funcional, não produção. Antes de uso real: MySQL, password_hash/password_verify, CSRF,
rate limiting, recuperação de senha, logs/auditoria, políticas de documentos, LGPD, backups e revisão de permissões.
