"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

export default function PaginaLogin() {
  const router = useRouter();
  const [cpf, setCpf] = useState("");
  const [senha, setSenha] = useState("");
  const [erro, setErro] = useState("");
  const [carregando, setCarregando] = useState(false);

  async function entrar(e: React.FormEvent) {
    e.preventDefault();
    setErro("");
    setCarregando(true);

    const resposta = await fetch("/api/auth/login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ cpf, senha }),
    });

    setCarregando(false);

    if (!resposta.ok) {
      const dados = await resposta.json();
      setErro(dados.erro || "Não foi possível entrar.");
      return;
    }

    router.push("/inicio");
    router.refresh();
  }

  return (
    <div className="min-h-screen grid place-items-center bg-[#0B3742] px-4">
      <form
        onSubmit={entrar}
        className="bg-white rounded-2xl p-8 w-full max-w-md"
      >
        <div className="flex items-center gap-3 mb-6">
          <div className="w-10 h-10 rounded-lg bg-[#DCEBEE] grid place-items-center font-serif-brand font-semibold">
            TSP
          </div>
          <div>
            <b className="block font-serif-brand text-lg">Área do Cliente</b>
            <small className="text-[var(--muted)]">Acompanhe seus processos com a TSP</small>
          </div>
        </div>

        <label className="block mb-4">
          <span className="font-semibold block mb-1">CPF</span>
          <input
            className="w-full border border-[var(--line)] rounded-md px-3 py-2"
            value={cpf}
            onChange={(e) => setCpf(e.target.value)}
            placeholder="000.000.000-00"
            inputMode="numeric"
          />
        </label>

        <label className="block mb-2">
          <span className="font-semibold block mb-1">Senha</span>
          <input
            className="w-full border border-[var(--line)] rounded-md px-3 py-2"
            type="password"
            value={senha}
            onChange={(e) => setSenha(e.target.value)}
          />
        </label>

        {erro && <p className="text-[var(--bad)] font-semibold mt-2">{erro}</p>}

        <button
          className="w-full mt-5 bg-[var(--brand)] text-white font-semibold rounded-md py-3 disabled:opacity-60"
          disabled={carregando}
        >
          {carregando ? "Entrando..." : "Entrar"}
        </button>
      </form>
    </div>
  );
}
