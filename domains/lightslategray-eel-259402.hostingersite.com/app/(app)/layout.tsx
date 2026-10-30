import Link from "next/link";
import { redirect } from "next/navigation";
import { clienteIdDaSessao } from "@/lib/auth";
import { prisma } from "@/lib/db";

const NAV = [
  { href: "/inicio", label: "Início" },
  { href: "/processos", label: "Processos" },
  { href: "/financeiro", label: "Financeiro" },
  { href: "/chamados", label: "Chamados" },
];

export default async function LayoutApp({ children }: { children: React.ReactNode }) {
  const clienteId = await clienteIdDaSessao();
  if (!clienteId) redirect("/login");

  const cliente = await prisma.cliente.findUnique({ where: { id: clienteId } });
  if (!cliente) redirect("/login");

  const iniciais = cliente.nome
    .split(" ")
    .slice(0, 2)
    .map((p) => p[0])
    .join("")
    .toUpperCase();

  return (
    <div className="grid grid-cols-[252px_1fr] min-h-screen">
      <aside className="bg-[#0B3742] text-[#E4EFF1] p-4 flex flex-col gap-7">
        <div className="flex items-center gap-3 px-2">
          <div className="w-10 h-10 rounded-lg bg-[#DCEBEE] text-[#0B3742] grid place-items-center font-serif-brand font-semibold">
            TSP
          </div>
          <div>
            <b className="block font-serif-brand text-lg leading-tight">Área do Cliente</b>
          </div>
        </div>

        <nav className="flex flex-col gap-1">
          {NAV.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className="px-3 py-2.5 rounded-lg font-medium hover:bg-white/10"
            >
              {item.label}
            </Link>
          ))}
        </nav>

        <div className="mt-auto flex items-center gap-3 pt-3 border-t border-white/10">
          <div className="w-9 h-9 rounded-full bg-[#DCEBEE] text-[#0B3742] grid place-items-center font-bold text-sm">
            {iniciais}
          </div>
          <div className="min-w-0 flex-1">
            <b className="block text-sm truncate">{cliente.nome}</b>
            <form action="/api/auth/logout" method="POST">
              <button className="text-sm text-[#9CB8BF] hover:text-[#E4EFF1]">Sair</button>
            </form>
          </div>
        </div>
      </aside>

      <main className="p-10 max-w-[1080px]">{children}</main>
    </div>
  );
}
