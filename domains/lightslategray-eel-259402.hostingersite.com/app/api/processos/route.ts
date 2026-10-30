import { NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { clienteIdDaSessao } from "@/lib/auth";
import { clienteEstaEmDia } from "@/lib/financeiro";

export async function GET() {
  const clienteId = await clienteIdDaSessao();
  if (!clienteId) return NextResponse.json({ erro: "Não autenticado." }, { status: 401 });

  const emDia = await clienteEstaEmDia(clienteId);

  const processos = await prisma.processo.findMany({
    where: { clientes: { some: { clienteId } } },
  });

  // Regra central de bloqueio: SE o cliente estiver em atraso, os detalhes do
  // processo não saem do servidor. Isso é intencional — o front-end nunca
  // deve precisar "esconder" dados sensíveis sozinho, porque dados que chegam
  // ao navegador podem ser lidos pelo DevTools mesmo com CSS de desfoque.
  const dados = processos.map((p) => ({
    id: p.id,
    titulo: p.titulo,
    numero: p.numero,
    bloqueado: !emDia,
    ...(emDia
      ? {
          vara: p.vara,
          fases: p.fases,
          faseAtual: p.faseAtual,
          status: p.status,
          proximoPasso: p.proximoPasso,
        }
      : {}),
  }));

  return NextResponse.json({ emDia, processos: dados });
}
