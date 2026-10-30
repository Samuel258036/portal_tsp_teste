import "./globals.css";

export const metadata = {
  title: "Área do Cliente | TSP Advogados",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="pt-BR">
      <body>{children}</body>
    </html>
  );
}
