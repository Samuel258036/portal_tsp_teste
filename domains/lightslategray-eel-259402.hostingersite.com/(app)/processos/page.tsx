import Link from "next/link";
import { clienteIdDaSessao } from "@/lib/auth";
import { clienteEstaEmDia } from "@/lib/financeiro";
import { prisma } from "@/lib/db";

export default async function PaginaProcessos() {
  const clienteId = (await clienteIdDaSessao())!;
  const emDia = await clienteEstaEmDia(clienteId);

  const processos = await prisma.processo.findMany({
    where: { clientes: { some: { clienteId } } },
  });

  return (
    <div>
      <h1 className="font-serif-brand text-4xl">Seus processos</h1>
      <p className="text-[var(--muted)] mt-2">
        {emDia
          ? "Toque em um processo para ver o resumo e as movimentações."
          : "O acompanhamento está bloqueado por uma parcela em atraso."}
      </p>

      <div className="flex flex-col gap-4 mt-6">
        {processos.map((p) => (
          <Link
            key={p.id}
            href={`/processos/${p.id}`}
            className="bg-white border border-[var(--line)] rounded-xl p-5 block"
          >
            <div className="flex justify-between items-start gap-3">
              <div>
                <h3 className="font-serif-brand text-xl">{p.titulo}</h3>
                <div className="text-[var(--muted)] text-sm mt-1">Nº {p.numero}</div>
              </div>
              <span
                className={`text-xs font-bold rounded-full px-3 py-1 ${
                  emDia ? "bg-[var(--ok-bg)] text-[var(--ok)]" : "bg-[var(--bad-bg)] text-[var(--bad)]"
                }`}
              >
                {emDia ? "Atualizado" : "Bloqueado"}
              </span>
            </div>
            {!emDia && (
              <p className="text-[var(--bad)] text-sm font-semibold mt-3">
                Regularize o financeiro para ver os detalhes
              </p>
            )}
          </Link>
        ))}
      </div>
    </div>
  );
}
