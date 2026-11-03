import Link from "next/link";
import { clienteIdDaSessao } from "@/lib/auth";
import { clienteEstaEmDia } from "@/lib/financeiro";
import { prisma } from "@/lib/db";

export default async function PaginaInicio() {
  const clienteId = (await clienteIdDaSessao())!;
  const cliente = await prisma.cliente.findUnique({ where: { id: clienteId } });
  const emDia = await clienteEstaEmDia(clienteId);

  return (
    <div>
      <h1 className="font-serif-brand text-4xl">Olá, {cliente?.nome.split(" ")[0]}.</h1>

      <div
        className={`mt-6 rounded-xl p-5 flex items-center gap-4 ${
          emDia ? "bg-[var(--ok-bg)] text-[var(--ok)]" : "bg-[var(--bad-bg)] text-[var(--bad)]"
        }`}
      >
        <div className="flex-1">
          <b className="block text-[var(--ink)]">
            {emDia ? "Seu financeiro está em dia" : "Há uma parcela em atraso"}
          </b>
          <p className="text-[var(--ink)] opacity-80 mt-0.5">
            {emDia
              ? "Nenhuma pendência no momento."
              : "Regularize para voltar a ver os detalhes dos seus processos."}
          </p>
        </div>
        <Link
          href="/financeiro"
          className="bg-[var(--brand)] text-white font-semibold rounded-md px-4 py-2.5"
        >
          Ver financeiro
        </Link>
      </div>

      <div className="grid grid-cols-3 gap-4 mt-8">
        <Link href="/processos" className="bg-white border border-[var(--line)] rounded-xl p-5 block">
          <span className="block text-[var(--muted)]">Processos</span>
          <span className="font-serif-brand text-2xl">Ver andamento</span>
        </Link>
        <Link href="/financeiro" className="bg-white border border-[var(--line)] rounded-xl p-5 block">
          <span className="block text-[var(--muted)]">Financeiro</span>
          <span className="font-serif-brand text-2xl">Parcelas e pagamentos</span>
        </Link>
        <Link href="/chamados" className="bg-white border border-[var(--line)] rounded-xl p-5 block">
          <span className="block text-[var(--muted)]">Chamados</span>
          <span className="font-serif-brand text-2xl">Falar com a equipe</span>
        </Link>
      </div>
    </div>
  );
}
