"use client";

import { useEffect, useState } from "react";

interface Mensagem {
  id: string;
  autorTipo: string;
  texto: string;
  criadaEm: string;
}
interface Chamado {
  id: string;
  assunto: string;
  categoria: string;
  status: string;
  criadoEm: string;
  processo?: { titulo: string } | null;
  mensagens: Mensagem[];
}

const ROTULO: Record<string, string> = {
  recebido: "Recebido",
  em_andamento: "Em andamento",
  respondido: "Respondido",
  concluido: "Concluído",
};

export default function PaginaChamados() {
  const [chamados, setChamados] = useState<Chamado[]>([]);
  const [novo, setNovo] = useState(false);
  const [mensagem, setMensagem] = useState("");
  const [categoria, setCategoria] = useState("Dúvida sobre o processo");
  const [erro, setErro] = useState("");

  async function carregar() {
    const r = await fetch("/api/chamados");
    const d = await r.json();
    setChamados(d.chamados ?? []);
  }

  useEffect(() => {
    carregar();
  }, []);

  async function enviar(e: React.FormEvent) {
    e.preventDefault();
    if (!mensagem.trim()) {
      setErro("Descreva sua dúvida para enviarmos ao time certo.");
      return;
    }
    setErro("");
    await fetch("/api/chamados", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ categoria, mensagem }),
    });
    setMensagem("");
    setNovo(false);
    carregar();
  }

  if (novo) {
    return (
      <div>
        <button onClick={() => setNovo(false)} className="text-[var(--brand)] font-semibold">
          ← Voltar para chamados
        </button>
        <h1 className="font-serif-brand text-4xl mt-3">Novo chamado</h1>

        <form onSubmit={enviar} className="bg-white border border-[var(--line)] rounded-xl p-6 mt-6 flex flex-col gap-4">
          <label>
            <span className="font-semibold block mb-1">Assunto</span>
            <select
              className="w-full border border-[var(--line)] rounded-md px-3 py-2"
              value={categoria}
              onChange={(e) => setCategoria(e.target.value)}
            >
              <option>Dúvida sobre o processo</option>
              <option>Enviar documento</option>
              <option>Financeiro e pagamentos</option>
              <option>Outro assunto</option>
            </select>
          </label>

          <label>
            <span className="font-semibold block mb-1">Mensagem</span>
            <textarea
              className="w-full border border-[var(--line)] rounded-md px-3 py-2 min-h-[130px]"
              value={mensagem}
              onChange={(e) => setMensagem(e.target.value)}
              placeholder="Escreva aqui sua dúvida ou o que você precisa"
            />
            {erro && <p className="text-[var(--bad)] font-semibold mt-1">{erro}</p>}
          </label>

          <div className="flex gap-3">
            <button className="bg-[var(--brand)] text-white font-semibold rounded-md px-5 py-2.5">
              Enviar chamado
            </button>
          </div>
        </form>
      </div>
    );
  }

  return (
    <div>
      <div className="flex justify-between items-start gap-4">
        <div>
          <h1 className="font-serif-brand text-4xl">Chamados</h1>
          <p className="text-[var(--muted)] mt-2">Tire dúvidas ou peça ajuda ao time da TSP.</p>
        </div>
        <button
          onClick={() => setNovo(true)}
          className="bg-[var(--brand)] text-white font-semibold rounded-md px-4 py-2.5"
        >
          + Abrir chamado
        </button>
      </div>

      <div className="bg-white border border-[var(--line)] rounded-xl mt-6 divide-y divide-[var(--line)]">
        {chamados.map((c) => (
          <div key={c.id} className="p-5">
            <div className="flex justify-between gap-3">
              <div>
                <b>{c.assunto}</b>
                <div className="text-[var(--muted)] text-sm">
                  #{c.id.slice(-5)} · {c.categoria} · {c.processo?.titulo ?? "Assunto geral"}
                </div>
              </div>
              <span className="text-xs font-bold rounded-full px-3 py-1 bg-[var(--brand-soft)] text-[var(--brand)] h-fit">
                {ROTULO[c.status] ?? c.status}
              </span>
            </div>
          </div>
        ))}
        {chamados.length === 0 && (
          <p className="p-5 text-[var(--muted)]">Nenhum chamado aberto ainda.</p>
        )}
      </div>
    </div>
  );
}
