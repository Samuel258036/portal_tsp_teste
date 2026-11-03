// Coleta de intimações do PJe Comunicações (comunica.pje.jus.br).
// Baseado no script Python levantado pelo dev (consulta direta à API REST do tribunal).
//
// Isso NÃO é chamado pelo navegador do cliente. É uma rotina de servidor,
// pensada para rodar periodicamente (ver scripts/coletar-pje.ts) e gravar
// as movimentações novas no banco. A área do cliente sempre lê do banco,
// nunca direto do PJe.
//
// Limite importante (confirmar com o escritório): essa API só cobre
// tribunais que usam o PJe. Processos em tribunais com e-SAJ (ex: parte do
// TJSP) não aparecem aqui e precisam de outra fonte.

import { prisma } from "@/lib/db";

interface ItemPje {
  id: string;
  numero_processo: string;
  siglaTribunal: string;
  nomeOrgao: string;
  data_disponibilizacao: string;
  texto: string;
}

function limparTextoHtml(html: string): string {
  // Remoção simples de tags. Para um parsing mais robusto como o BeautifulSoup
  // do script original, considere a lib "cheerio" (equivalente em Node).
  return html
    .replace(/<(script|style)[^>]*>[\s\S]*?<\/\1>/gi, "")
    .replace(/<[^>]+>/g, " ")
    .replace(/\s+/g, " ")
    .trim();
}

async function consultarPjeComunica(oab: string, uf: string, data: string): Promise<ItemPje[]> {
  const url =
    `https://comunicaapi.pje.jus.br/api/v1/comunicacao?numeroOab=${oab}` +
    `&ufOab=${uf}&dataDisponibilizacaoInicio=${data}&dataDisponibilizacaoFim=${data}`;

  const resposta = await fetch(url, {
    headers: { "User-Agent": "Mozilla/5.0", Accept: "application/json" },
  });

  if (!resposta.ok) {
    console.error("Erro ao consultar PJe Comunica:", resposta.status);
    return [];
  }

  const json = await resposta.json();
  return json.items ?? [];
}

// Associa a intimação ao processo certo (pelo número CNJ) e grava no banco,
// evitando duplicar (idExternoPje é único).
export async function coletarIntimacoesDoDia(dataISO: string) {
  const oab = process.env.PJE_OAB_NUMERO!;
  const uf = process.env.PJE_OAB_UF!;

  const itens = await consultarPjeComunica(oab, uf, dataISO);
  let novas = 0;

  for (const item of itens) {
    const processo = await prisma.processo.findUnique({
      where: { numero: item.numero_processo },
    });

    // Se o processo ainda não está cadastrado no sistema, a intimação fica
    // sem lar por enquanto. Decisão de produto a confirmar com o escritório:
    // cadastrar o processo automaticamente aqui, ou deixar para um membro
    // da equipe revisar manualmente intimações "órfãs" antes de criar o vínculo.
    if (!processo) continue;

    const existente = await prisma.movimentacao.findUnique({
      where: { idExternoPje: item.id },
    });
    if (existente) continue;

    await prisma.movimentacao.create({
      data: {
        processoId: processo.id,
        data: new Date(item.data_disponibilizacao),
        tituloCurto: "Nova intimação", // TODO: refinar com um resumo automático do tipo de ato
        textoOriginal: limparTextoHtml(item.texto),
        fonteOrigem: "pje_comunica",
        idExternoPje: item.id,
      },
    });
    novas++;

    // Dispara a geração do resumo em IA para essa movimentação nova.
    // (ver src/lib/integracoes/resumo-ia.ts)
  }

  return { total: itens.length, novas };
}
