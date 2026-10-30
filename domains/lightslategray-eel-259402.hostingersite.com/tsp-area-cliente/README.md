# Área do Cliente — TSP Advogados

Esqueleto funcional: login, processos, financeiro e chamados, prontos para
rodar. PJe e IA já têm a lógica escrita, faltando só ligar na rotina
automática (cron). Pagamento via Pix está como integração a fazer — ver nota
no final.

Stack: Next.js (App Router) + TypeScript + Prisma + MySQL.

## 1. Banco de dados (Hostinger)

1. No **hPanel**, crie um banco de dados MySQL **novo e dedicado** a este
   projeto (separado do banco do site institucional).
2. Ainda no hPanel, vá em **MySQL Remoto** e libere o IP do servidor onde o
   backend vai rodar (ver passo 4). Sem isso, o backend não consegue falar
   com o banco se estiver fora da Hostinger.
3. Anote: host, nome do banco, usuário e senha.

## 2. Configuração local

```bash
cd tsp-area-cliente
npm install
cp .env.example .env
```

Abra o `.env` e preencha:
- `DATABASE_URL` com os dados do passo 1.
- `SESSION_SECRET` com uma string aleatória longa (`openssl rand -base64 32`).
- `ANTHROPIC_API_KEY` com uma chave da Console da Anthropic (console.anthropic.com).
- `PJE_OAB_NUMERO` e `PJE_OAB_UF` com os dados da OAB do escritório.

## 3. Criar as tabelas e popular dados de teste

```bash
npm run db:push   # cria as tabelas no MySQL a partir de prisma/schema.prisma
npm run seed       # cria um cliente e um processo de teste
npm run dev         # roda em http://localhost:3000
```

Login de teste: CPF `123.456.789-00`, senha `demonstracao`.

Para testar o bloqueio por atraso, abra o Prisma Studio (`npm run db:studio`)
e mude o status da parcela 4 para `"atrasada"`.

## 4. Deploy

Recomendado: **Vercel** (gratuito para começar, feito para Next.js).

1. Suba este projeto para um repositório no GitHub.
2. Na Vercel, importe o repositório.
3. Em "Environment Variables", cole as mesmas variáveis do seu `.env`.
4. Deploy. A Vercel te dá uma URL (ex: `tsp-cliente.vercel.app`).
5. No seu domínio, crie um registro **CNAME** apontando
   `cliente.tsp.adv.br` para a URL que a Vercel indicar nas configurações de
   domínio do projeto (ela mostra o passo a passo exato quando você adiciona
   o domínio customizado por lá).
6. No **hPanel da Hostinger**, em "DNS / Nameservers" da zona de `tsp.adv.br`,
   adicione esse mesmo CNAME.
7. No Construtor de Sites da Hostinger, adicione um botão "Área do Cliente"
   apontando para `https://cliente.tsp.adv.br`.

## 5. O que ainda falta construir (próximos passos, em ordem sugerida)

1. **Painel interno da equipe** — hoje só existe a área do cliente. Falta uma
   tela (mesmo que simples) para a equipe: responder chamados, aprovar
   resumos de IA pendentes, e cadastrar processos/parcelas manualmente.
2. **Cron da coleta do PJe** — `src/lib/integracoes/pje.ts` já tem a função
   pronta (`coletarIntimacoesDoDia`). Falta um agendador chamando ela 1x por
   dia (ex: Vercel Cron Jobs, ou um cron job simples na Hostinger).
3. **Geração automática do resumo de IA** — depois que o cron grava
   movimentações novas, chamar `gerarResumoPendente(processoId)`
   (`src/lib/integracoes/resumo-ia.ts`) para cada processo que mudou.
4. **Pagamento via Pix** — integrar com um gateway (Asaas, Iugu, Mercado
   Pago...). O gateway gera a cobrança e, quando o Pix é pago, chama um
   webhook do seu sistema (a criar em `/api/webhooks/pagamento`) que marca a
   parcela como `"paga"`. A liberação do processo já é automática a partir
   daí, porque `clienteEstaEmDia()` é checada a cada acesso.
5. **Cron de atraso** — chamar `atualizarParcelasVencidas()`
   (`src/lib/financeiro.ts`) todo dia, para marcar parcelas vencidas como
   atrasadas automaticamente.
6. **Primeiro acesso / recuperação de senha** — hoje o cadastro de cliente é
   manual (via Prisma Studio ou uma tela interna a criar). Fazer um fluxo de
   "defina sua senha" por e-mail no primeiro acesso.

## Observação sobre segurança

A regra de bloqueio por atraso acontece **no servidor** (veja
`src/app/api/processos/route.ts`): se o cliente está em atraso, os dados do
processo simplesmente não são enviados ao navegador. Isso é proposital — um
bloqueio só em CSS (desfocar na tela) pode ser contornado pelo DevTools do
navegador.
