import { NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { clienteIdDaSessao } from "@/lib/auth";

export async function GET() {
  const clienteId = await clienteIdDaSessao();
  if (!clienteId) return NextResponse.json({ erro: "Não autenticado." }, { status: 401 });

  const parcelas = await prisma.parcela.findMany({
    where: { clienteId },
    orderBy: { numero: "asc" },
  });

  return NextResponse.json({ parcelas });
}

// NOTA: o pagamento em si (gerar cobrança Pix, confirmar pagamento) não acontece
// aqui. Isso é feito pelo gateway de pagamento (Asaas, Iugu, etc). O fluxo correto é:
//   1. Esta rota só LÊ o status das parcelas, que já foram criadas via integração com o gateway.
//   2. O gateway envia um webhook para /api/webhooks/pagamento quando um Pix é pago.
//   3. Esse webhook (a implementar) atualiza a parcela para status "paga" e, se era a
//      última em atraso, o cliente volta a ver os processos automaticamente —
//      porque a liberação já é checada "ao vivo" por clienteEstaEmDia() a cada acesso.
