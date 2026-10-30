// Popula o banco com um cliente e um processo de teste, para o dev validar
// o fluxo de ponta a ponta antes de ligar as integrações reais.
// Rodar com: npm run seed

import { PrismaClient } from "@prisma/client";
import bcrypt from "bcryptjs";

const prisma = new PrismaClient();

async function main() {
  const senhaHash = await bcrypt.hash("demonstracao", 12);

  const cliente = await prisma.cliente.upsert({
    where: { cpf: "12345678900" },
    update: {},
    create: {
      nome: "Mariana Alves Costa",
      cpf: "12345678900",
      email: "mariana@exemplo.com",
      senhaHash,
    },
  });

  const processo = await prisma.processo.upsert({
    where: { numero: "1023456-78.2025.8.26.0554" },
    update: {},
    create: {
      numero: "1023456-78.2025.8.26.0554",
      titulo: "Ação de indenização por danos morais",
      vara: "3ª Vara Cível de Santo André",
      tribunal: "TJSP",
      fases: ["Petição inicial", "Citação", "Contestação", "Provas", "Sentença", "Recurso"],
      faseAtual: 3,
      status: "Aguardando audiência",
      proximoPasso: "Audiência em 14/10/2026, às 14h30",
    },
  });

  await prisma.processoCliente.upsert({
    where: { processoId_clienteId: { processoId: processo.id, clienteId: cliente.id } },
    update: {},
    create: { processoId: processo.id, clienteId: cliente.id },
  });

  // 6 parcelas: as 3 primeiras pagas, a 4ª propositalmente SEM pagamento
  // (fica "aberta" até o cron marcar como atrasada, ou troque o status manualmente
  // para "atrasada" aqui para testar o bloqueio imediatamente).
  for (let n = 1; n <= 6; n++) {
    await prisma.parcela.create({
      data: {
        clienteId: cliente.id,
        numero: n,
        valorCentavos: 200000,
        vencimento: new Date(2026, 5 + n, 10),
        status: n <= 3 ? "paga" : "aberta",
        pagoEm: n <= 3 ? new Date(2026, 5 + n, 9) : null,
      },
    });
  }

  console.log("Seed concluído. Login de teste: CPF 123.456.789-00 / senha: demonstracao");
}

main().finally(() => prisma.$disconnect());
