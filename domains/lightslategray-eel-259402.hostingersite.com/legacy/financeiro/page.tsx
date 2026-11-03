import { clienteIdDaSessao } from "@/lib/auth";
import { prisma } from "@/lib/db";

function brl(centavos: number) {
  return (centavos / 100).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

const ROTULO_STATUS: Record<string, { texto: string; cor: string }> = {
  paga: { texto: "Paga", cor: "bg-[var(--ok-bg)] text-[var(--ok)]" },
  atrasada: { texto: "Atrasada", cor: "bg-[var(--bad-bg)] text-[var(--bad)]" },
  aberta: { texto: "A vencer", cor: "bg-[var(--bg)] text-[var(--muted)] border border-[var(--line)]" },
};

export default async function PaginaFinanceiro() {
  const clienteId = (await clienteIdDaSessao())!;
  const parcelas = await prisma.parcela.findMany({
    where: { clienteId },
    orderBy: { numero: "asc" },
  });

  const temAtraso = parcelas.some((p) => p.status === "atrasada");

  return (
    <div>
      <h1 className="font-serif-brand text-4xl">Financeiro</h1>

      <div
        className={`mt-6 rounded-xl p-5 ${
          temAtraso ? "bg-[var(--bad-bg)] text-[var(--bad)]" : "bg-[var(--ok-bg)] text-[var(--ok)]"
        }`}
      >
        <b className="block text-[var(--ink)]">
          {temAtraso ? "Há parcela(s) em atraso" : "Tudo em dia"}
        </b>
      </div>

      <div className="bg-white border border-[var(--line)] rounded-xl mt-6 overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-[var(--muted)] border-b border-[var(--line)]">
              <th className="p-4">Parcela</th>
              <th className="p-4">Vencimento</th>
              <th className="p-4">Valor</th>
              <th className="p-4">Situação</th>
            </tr>
          </thead>
          <tbody>
            {parcelas.map((p) => {
              const rotulo = ROTULO_STATUS[p.status] ?? ROTULO_STATUS.aberta;
              return (
                <tr key={p.id} className="border-t border-[var(--line)]">
                  <td className="p-4">Parcela {p.numero} de {parcelas.length}</td>
                  <td className="p-4">{p.vencimento.toLocaleDateString("pt-BR")}</td>
                  <td className="p-4">{brl(p.valorCentavos)}</td>
                  <td className="p-4">
                    <span className={`text-xs font-bold rounded-full px-3 py-1 ${rotulo.cor}`}>
                      {rotulo.texto}
                    </span>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      <p className="text-[var(--muted)] text-sm mt-4">
        O pagamento (Pix/boleto) acontece pela cobrança enviada pelo gateway de pagamento.
        Esta tela só reflete o status — ver nota técnica em <code>src/app/api/financeiro/route.ts</code>.
      </p>
    </div>
  );
}
