import { NextResponse } from "next/server";
import { apagarCookieSessao } from "@/lib/auth";

export async function POST() {
  apagarCookieSessao();
  return NextResponse.json({ ok: true });
}
