import Link from "next/link";
import { notFound } from "next/navigation";
import { clienteIdDaSessao } from "@/lib/auth";
import { clienteEstaEmDia } from "@/lib/financeiro";
import { prisma } from "@/lib/db";

export default async function PaginaProcesso({ params }: { params: { id: string } }) {
  const clienteId = (await clienteIdDaSessao())!;

  const vinculo = await prisma.processoCliente.findUnique({
    where: { processoId_clienteId: { processoId: params.id, clienteId } },
  });
  if (!vinculo) notFound();

  const emDia = await clienteEstaEmDia(clienteId);

  if (!emDia) {
    return (
      <div>
        <Link href="/processos" className="text-[var(--brand)] font-semibold">
          ← Voltar para processos
        </Link>
        <div className="mt-10 max-w-md mx-auto bg-white rounded-2xl p-7 text-center border border-[var(--line)]">
          <h3 className="font-serif-brand text-2xl">Acompanhamento bloqueado</h3>
          <p className="text-[var(--muted)] mt-2">
            Há uma pendência financeira. Regularize para voltar a ver o resumo e as
            movimentações deste processo.
          </p>
          <Link
            href="/financeiro"
            className="inline-block mt-5 bg-[var(--brand)] text-white font-semibold rounded-md px-5 py-2.5"
          >
            Ver financeiro
          </Link>
        </div>
      </div>
    );
  }

  const processo = await prisma.processo.findUnique({
    where: { id: params.id },
    include: {
      movimentacoes: { orderBy: { data: "desc" }, take: 20 },
      resumos: { where: { status: "aprovado" }, orderBy: { criadoEm: "desc" }, take: 1 },
    },
  });
  if (!processo) notFound();

  const resumo = processo.resumos[0];

  return (
    <div>
      <Link href="/processos" className="text-[var(--brand)] font-semibold">
        ← Voltar para processos
      </Link>

      <h1 className="font-serif-brand text-3xl mt-3">{processo.titulo}</h1>
      <div className="text-[var(--muted)] text-sm mt-1">
        Nº {processo.numero} {processo.vara ? `· ${processo.vara}` : ""}
      </div>

      {resumo ? (
        <div className="bg-white border border-[var(--line)] rounded-xl p-6 mt-6">
          <div className="text-[var(--brand)] font-semibold text-sm mb-2">
            O que está acontecendo, em palavras simples
          </div>
          <p className="font-serif-brand text-xl leading-relaxed">{resumo.textoGerado}</p>
          <div className="text-[var(--muted)] text-sm mt-4 pt-3 border-t border-dashed border-[var(--line)]">
            Resumo escrito por inteligência artificial e revisado pela equipe TSP.
          </div>
        </div>
      ) : (
        <p className="text-[var(--muted)] mt-6">Ainda não há um resumo para este processo.</p>
      )}

      <h2 className="font-serif-brand text-2xl mt-10 mb-3">Movimentações</h2>
      <div className="bg-white border border-[var(--line)] rounded-xl divide-y divide-[var(--line)]">
        {processo.movimentacoes.map((m) => (
          <div key={m.id} className="p-5">
            <div className="text-[var(--muted)] text-sm">
              {m.data.toLocaleDateString("pt-BR")}
            </div>
            <b className="block mt-0.5">{m.tituloCurto}</b>
            <p className="text-[var(--muted)] mt-1 text-sm">{m.textoOriginal}</p>
          </div>
        ))}
        {processo.movimentacoes.length === 0 && (
          <p className="p-5 text-[var(--muted)]">Nenhuma movimentação registrada ainda.</p>
        )}
      </div>
    </div>
  );
}
