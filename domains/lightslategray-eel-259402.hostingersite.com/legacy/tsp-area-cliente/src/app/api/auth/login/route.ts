import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";
import { conferirSenha, criarCookieSessao } from "@/lib/auth";

export async function POST(req: NextRequest) {
  const { cpf, senha } = await req.json();

  if (!cpf || !senha) {
    return NextResponse.json({ erro: "Informe CPF e senha." }, { status: 400 });
  }

  const cpfLimpo = String(cpf).replace(/\D/g, "");
  const cliente = await prisma.cliente.findFirst({
    where: { cpf: cpfLimpo },
  });

  // Mensagem genérica de propósito: não revelar se o CPF existe ou não.
  if (!cliente) {
    return NextResponse.json({ erro: "CPF ou senha incorretos." }, { status: 401 });
  }

  const senhaOk = await conferirSenha(senha, cliente.senhaHash);
  if (!senhaOk) {
    return NextResponse.json({ erro: "CPF ou senha incorretos." }, { status: 401 });
  }

  await criarCookieSessao(cliente.id);

  return NextResponse.json({ ok: true });
}
