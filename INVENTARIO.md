# INVENTÁRIO DO SISTEMA - TSP PORTAL

---

## 1. Mapeamento de Arquivos PHP

O projeto encontra-se estruturado em diretórios de versões e ambientes (`novo`, `Teste`, `V2`, `v3`, `v4`). Abaixo está o inventário agrupado por localização:

### Raiz
* `domains\lightslategray-eel-259402.hostingersite.com\tsp-juridiq-secret.php`

### Diretório `novo/` (12 arquivos)
* `atualizacoes.php`
* `auth.php`
* `chamados.php`
* `clientes.php`
* `dashboard.php`
* `financeiro.php`
* `index.php`
* `integracoes.php`
* `logout.php`
* `portal-layout.php`
* `processos.php`
* `usuarios.php`

### Diretório `public_html/Teste/` (8 arquivos)
* `chamados.php`
* `config.php`
* `financeiro.php`
* `index.php`
* `inicio.php`
* `logout.php`
* `partials.php`
* `processos.php`

### Diretório `public_html/V2/` (11 arquivos)
* `chamados.php`
* `clientes.php`
* `config.php`
* `dashboard.php`
* `financeiro.php`
* `index.php`
* `logout.php`
* `movimentacoes.php`
* `partials.php`
* `processos.php`
* `usuarios.php`

### Diretório `public_html/v3/` (11 arquivos)
* `chamados.php`
* `clientes.php`
* `config.php`
* `dashboard.php`
* `financeiro.php`
* `index.php`
* `logout.php`
* `movimentacoes.php`
* `partials.php`
* `processos.php`
* `usuarios.php`

### Diretório `public_html/v4/` (18 arquivos)
* `atualizacoes.php`
* `auth.php`
* `chamados.php`
* `clientes-db.php`
* `clientes.php`
* `config.php`
* `dashboard.php`
* `database.php`
* `financeiro.php`
* `index.php`
* `integracoes.php`
* `logout.php`
* `movimentacoes.php`
* `partials.php`
* `portal-layout.php`
* `processos.php`
* `usuarios-db.php`
* `usuarios.php`

---

## 2. Tabelas do Banco de Dados MySQL

Total de **14 tabelas** identificadas na base de dados:

| Tabela | Qtd. Registros |
| :--- | :---: |
| `audit_logs` | 0 |
| `cases` | 1 |
| `case_integrations` | 0 |
| `case_updates` | 1 |
| `clients` | 2 |
| `documents` | 0 |
| `financial_contracts` | 0 |
| `financial_installments` | 0 |
| `integrations` | 2 |
| `notifications` | 0 |
| `sync_logs` | 0 |
| `tickets` | 0 |
| `ticket_messages` | 0 |
| `users` | 4 |

---

## 3. Perfis de Acesso e Usuários

| ID | Nome | Email | Role (Perfil) |
| :---: | :--- | :--- | :---: |
| **1** | Thiago Giro | `thiago.sp@tsp.adv.br` | `admin` |
| **2** | Teste Thiago | `teste@gmail.com` | *N/A* |
| **4** | Admin Teste | `teste@tsp.adv.br` | `admin` |
| **5** | Samuel Prado | `samuel.prado@tsp.adv.br` | `admin` |

---

## 4. Mapeamento de Integrações

### 1. Conexão de Banco de Dados (`database.php`)
* **Papel:** Gestão do ciclo de vida da conexão PDO com o MySQL local e de produção.
* **Segurança:** Utiliza variáveis de ambiente (`config.env.php` / `.env`) para evitar exposição de credenciais no código-fonte.

### 2. Automação PJe (`pje.ts`)
* **Papel:** Automação de consultas e extração de publicações/intimações do tribunal.
* **Dependências:** Runtime Node.js / TypeScript.
* **Variáveis Requeridas:** `PJE_OAB_NUMERO`, `PJE_OAB_UF`.

### 3. Módulo de Inteligência Artificial (`resumo-ia.ts`)
* **Papel:** Geração de resumos e relatórios jurídicos automatizados a partir dos dados extraídos do PJe.
* **Dependências:** Provedor Anthropic (Claude API).
* **Variáveis Requeridas:** `ANTHROPIC_API_KEY`.

### 4. Módulo de API do JURIDIQ
* **Papel:** Conexão com o sistema do Juridiq
* **Dependências:** sistema do Juridiq.
* **Variáveis Requeridas:** `JURIDIQ_API_KEY`.

---

## 5. Mapeamento de Variáveis de Ambiente (`.env`)

```env
SESSION_SECRET        # Chave de criptografia para gestão de sessões
DATABASE_URL          # String de conexão com o banco MySQL
PJE_OAB_NUMERO        # Número da inscrição OAB para raspagem
PJE_OAB_UF            # Estado da federação da OAB (ex: SP)
ANTHROPIC_API_KEY     # Chave de API para resumo automatizado via IA
JURIDIQ_API_KEY       # Chave de API para o sistema do JURIDIQ
```