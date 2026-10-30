import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { clienteIdDaSessao } from "@/lib/auth";

export async function POST(req: NextRequest, { params }: { params: { id: string } }) {
  const clienteId = await clienteIdDaSessao();
  if (!clienteId) return NextResponse.json({ erro: "Não autenticado." }, { status: 401 });

  const chamado = await prisma.chamado.findUnique({ where: { id: params.id } });
  if (!chamado || chamado.clienteId !== clienteId) {
    return NextResponse.json({ erro: "Chamado não encontrado." }, { status: 404 });
  }

  const { texto } = await req.json();
  if (!texto || !String(texto).trim()) {
    return NextResponse.json({ erro: "Escreva uma mensagem." }, { status: 400 });
  }

  const nova = await prisma.mensagemChamado.create({
    data: { chamadoId: params.id, autorTipo: "cliente", texto },
  });

  // Se o cliente responde um chamado já respondido pela equipe, volta para "em_andamento".
  await prisma.chamado.update({
    where: { id: params.id },
    data: { status: "em_andamento" },
  });

  return NextResponse.json({ mensagem: nova }, { status: 201 });
}
