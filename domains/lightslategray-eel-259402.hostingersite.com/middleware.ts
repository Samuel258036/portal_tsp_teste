import { NextRequest, NextResponse } from "next/server";
import { jwtVerify } from "jose";

// Middleware roda antes de qualquer página protegida.
// Se não houver cookie de sessão válido, manda para o login.
export async function middleware(req: NextRequest) {
  const token = req.cookies.get("tsp_sessao")?.value;

  if (!token) {
    return NextResponse.redirect(new URL("/login", req.url));
  }

  try {
    const secret = new TextEncoder().encode(process.env.SESSION_SECRET);
    await jwtVerify(token, secret);
    return NextResponse.next();
  } catch {
    return NextResponse.redirect(new URL("/login", req.url));
  }
}

// Protege tudo, exceto /login e os próprios assets do Next.
export const config = {
  matcher: ["/((?!login|api/auth/login|_next/static|_next/image|favicon.ico).*)"],
};
