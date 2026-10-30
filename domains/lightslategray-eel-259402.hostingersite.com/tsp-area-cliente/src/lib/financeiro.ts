import { prisma } from "@/lib/db";

// Regra de negócio única para "o cliente está em dia?".
// Centralizar aqui evita que cada rota implemente a regra de um jeito diferente.
// Hoje: em dia = nenhuma parcela com status "atrasada".
// Ajuste a tolerância de dias aqui, se o escritório quiser uma carência.
export async function clienteEstaEmDia(clienteId: string): Promise<boolean> {
  const atrasada = await prisma.parcela.findFirst({
    where: { clienteId, status: "atrasada" },
  });
  return !atrasada;
}

// Rotina a ser chamada por um cron job (ex: 1x por dia) para atualizar
// parcelas vencidas de "aberta" para "atrasada".
export async function atualizarParcelasVencidas() {
  await prisma.parcela.updateMany({
    where: {
      status: "aberta",
      vencimento: { lt: new Date() },
    },
    data: { status: "atrasada" },
  });
}
