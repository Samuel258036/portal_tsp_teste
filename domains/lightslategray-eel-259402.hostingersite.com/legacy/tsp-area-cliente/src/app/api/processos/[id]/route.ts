import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { clienteIdDaSessao } from "@/lib/auth";
import { clienteEstaEmDia } from "@/lib/financeiro";

export async function GET(req: NextRequest, { params }: { params: { id: string } }) {
  const clienteId = await clienteIdDaSessao();
  if (!clienteId) return NextResponse.json({ erro: "Não autenticado." }, { status: 401 });

  // Confirma que o processo pertence mesmo a esse cliente — nunca confiar só no id da URL.
  const vinculo = await prisma.processoCliente.findUnique({
    where: { processoId_clienteId: { processoId: params.id, clienteId } },
  });
  if (!vinculo) return NextResponse.json({ erro: "Processo não encontrado." }, { status: 404 });

  const emDia = await clienteEstaEmDia(clienteId);
  if (!emDia) {
    return NextResponse.json({ bloqueado: true }, { status: 200 });
  }

  const processo = await prisma.processo.findUnique({
    where: { id: params.id },
    include: {
      movimentacoes: { orderBy: { data: "desc" }, take: 20 },
      resumos: {
        where: { status: "aprovado" },
        orderBy: { criadoEm: "desc" },
        take: 1,
      },
    },
  });

  return NextResponse.json({ bloqueado: false, processo });
}
