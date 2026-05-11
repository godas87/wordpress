const fs = require("fs");
const path = require("path");
const { minify } = require("terser");

function getTimestamp() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, "0");
  return (
    d.getFullYear() +
    pad(d.getMonth() + 1) +
    pad(d.getDate()) +
    "_" +
    pad(d.getHours()) +
    pad(d.getMinutes()) +
    pad(d.getSeconds())
  );
}

async function main() {
  const themeDir = path.resolve(__dirname, "..");
  const inputPath = path.join(themeDir, "assets/js/app.min.js");
  const outputPath = inputPath;
  const backupPath = inputPath + ".bak." + getTimestamp();

  if (!fs.existsSync(inputPath)) {
    throw new Error(`Arquivo não encontrado: ${inputPath}`);
  }

  const code = fs.readFileSync(inputPath, "utf8");

  const result = await minify(code, {
    compress: {
      passes: 2,
      defaults: true,
    },
    mangle: true,
    format: {
      comments: false,
    },
    sourceMap: false,
  });

  if (!result || typeof result.code !== "string") {
    throw new Error("Falha na minificação: result.code está vazio.");
  }

  // Backup antes de sobrescrever
  fs.copyFileSync(outputPath, backupPath);
  fs.writeFileSync(outputPath, result.code, "utf8");

  console.log("[minify:app] OK");
  console.log("  input:", inputPath);
  console.log("  backup:", backupPath);
}

main().catch((err) => {
  console.error("[minify:app] ERRO:", err && err.message ? err.message : err);
  process.exitCode = 1;
});

