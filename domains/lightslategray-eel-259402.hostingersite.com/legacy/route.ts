import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { clienteIdDaSessao } from "@/lib/auth";

export async function GET() {
  const clienteId = await clienteIdDaSessao();
  if (!clienteId) return NextResponse.json({ erro: "Não autenticado." }, { status: 401 });

  const chamados = await prisma.chamado.findMany({
    where: { clienteId },
    orderBy: { criadoEm: "desc" },
    include: { processo: { select: { titulo: true } }, mensagens: true },
  });

  return NextResponse.json({ chamados });
}

export async function POST(req: NextRequest) {
  const clienteId = await clienteIdDaSessao();
  if (!clienteId) return NextResponse.json({ erro: "Não autenticado." }, { status: 401 });

  const { assunto, categoria, processoId, mensagem } = await req.json();

  if (!mensagem || !String(mensagem).trim()) {
    return NextResponse.json({ erro: "Descreva sua dúvida ou necessidade." }, { status: 400 });
  }

  const chamado = await prisma.chamado.create({
    data: {
      clienteId,
      processoId: processoId || null,
      assunto: assunto || String(mensagem).slice(0, 60),
      categoria: categoria || "Outro assunto",
      mensagens: {
        create: { autorTipo: "cliente", texto: mensagem },
      },
    },
  });

  // TODO: notificar a equipe (e-mail/Slack) que um novo chamado chegou.

  return NextResponse.json({ chamado }, { status: 201 });
}
