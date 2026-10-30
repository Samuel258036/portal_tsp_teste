import bcrypt from "bcryptjs";
import { SignJWT, jwtVerify } from "jose";
import { cookies } from "next/headers";

const COOKIE_NAME = "tsp_sessao";
const DURACAO_SESSAO_SEGUNDOS = 60 * 60 * 24 * 7; // 7 dias

function getSecret() {
  const secret = process.env.SESSION_SECRET;
  if (!secret) throw new Error("SESSION_SECRET não configurada no .env");
  return new TextEncoder().encode(secret);
}

// ---- senha ----

export async function hashSenha(senha: string) {
  return bcrypt.hash(senha, 12);
}

export async function conferirSenha(senha: string, hash: string) {
  return bcrypt.compare(senha, hash);
}

// ---- sessão (cookie httpOnly assinado, sem guardar sessão em tabela à parte) ----

export async function criarCookieSessao(clienteId: string) {
  const token = await new SignJWT({ clienteId })
    .setProtectedHeader({ alg: "HS256" })
    .setIssuedAt()
    .setExpirationTime(`${DURACAO_SESSAO_SEGUNDOS}s`)
    .sign(getSecret());

  cookies().set(COOKIE_NAME, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: DURACAO_SESSAO_SEGUNDOS,
  });
}

export function apagarCookieSessao() {
  cookies().delete(COOKIE_NAME);
}

// Lê o cookie da requisição atual e devolve o clienteId, ou null se não houver sessão válida.
export async function clienteIdDaSessao(): Promise<string | null> {
  const token = cookies().get(COOKIE_NAME)?.value;
  if (!token) return null;
  try {
    const { payload } = await jwtVerify(token, getSecret());
    return (payload.clienteId as string) ?? null;
  } catch {
    return null; // token inválido ou expirado
  }
}
