// Geração do resumo em linguagem simples, a partir das movimentações recentes
// de um processo. O resumo nasce com status "pendente" e só fica visível ao
// cliente depois que um membro da equipe aprova (ver fluxo abaixo).
//
// Chamada típica: depois que coletarIntimacoesDoDia() grava movimentações novas,
// rode gerarResumoPendente(processoId) para esse processo.

import { prisma } from "@/lib/db";

const PROMPT_SISTEMA = `Você é um assistente de um escritório de advocacia que explica
andamentos processuais para clientes leigos, em português do Brasil.
Regras:
- Linguagem simples, sem jargão jurídico. Se usar um termo técnico, explique em seguida.
- No máximo 2 parágrafos curtos.
- Nunca dê conselho jurídico, opinião sobre o mérito ou previsão de resultado.
- Foque em: o que mudou, o que isso significa na prática, e se o cliente precisa fazer algo.
- Se não houver nenhuma ação necessária do cliente, diga isso explicitamente.`;

export async function gerarResumoPendente(processoId: string) {
  const processo = await prisma.processo.findUnique({
    where: { id: processoId },
    include: { movimentacoes: { orderBy: { data: "desc" }, take: 5 } },
  });
  if (!processo) throw new Error("Processo não encontrado.");

  const textoMovimentacoes = processo.movimentacoes
    .map((m) => `[${m.data.toLocaleDateString("pt-BR")}] ${m.textoOriginal}`)
    .join("\n\n");

  const resposta = await fetch("https://api.anthropic.com/v1/messages", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "x-api-key": process.env.ANTHROPIC_API_KEY!,
      "anthropic-version": "2023-06-01",
    },
    body: JSON.stringify({
      model: "claude-sonnet-4-6",
      max_tokens: 500,
      system: PROMPT_SISTEMA,
      messages: [
        {
          role: "user",
          content: `Processo: ${processo.titulo}\n\nMovimentações recentes:\n${textoMovimentacoes}\n\nEscreva o resumo para o cliente.`,
        },
      ],
    }),
  });

  if (!resposta.ok) {
    throw new Error(`Erro na API da Anthropic: ${resposta.status}`);
  }

  const dados = await resposta.json();
  const texto = dados.content?.[0]?.text ?? "";

  // Nasce pendente — só aparece ao cliente depois de um membro da equipe
  // aprovar via painel interno (a construir: tela simples de fila de aprovação).
  return prisma.resumoIA.create({
    data: { processoId, textoGerado: texto, status: "pendente" },
  });
}

// Chamada pelo painel interno quando um membro da equipe aprova o resumo.
export async function aprovarResumo(resumoId: string, membroId: string) {
  return prisma.resumoIA.update({
    where: { id: resumoId },
    data: { status: "aprovado", revisadoPorId: membroId, revisadoEm: new Date() },
  });
}
