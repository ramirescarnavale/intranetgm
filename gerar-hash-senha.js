// Rode este script você mesma, no seu próprio terminal (fora da conversa com o Claude):
//   node gerar-hash-senha.js
// A senha nunca aparece na tela nem é enviada a lugar nenhum — fica só no seu computador.
// Copie a linha final ("pbkdf2:...") e cole no secrets.php.

const crypto = require('crypto');
const readline = require('readline');

function lerSenhaOculta(pergunta) {
  return new Promise((resolve) => {
    const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
    const escreverOriginal = rl._writeToOutput;
    rl._writeToOutput = function (str) {
      // Não ecoa os caracteres digitados da senha.
      if (str.includes('\r') || str.includes('\n')) escreverOriginal.call(rl, str);
    };
    rl.question(pergunta, (senha) => {
      rl.close();
      process.stdout.write('\n');
      resolve(senha);
    });
  });
}

(async () => {
  const senha = await lerSenhaOculta('Digite a senha (não vai aparecer na tela): ');
  const confirmacao = await lerSenhaOculta('Digite de novo para confirmar: ');

  if (senha !== confirmacao) {
    console.error('\nAs senhas não coincidem. Rode o script de novo.');
    process.exit(1);
  }
  if (senha.length < 8) {
    console.error('\nUse uma senha com pelo menos 8 caracteres.');
    process.exit(1);
  }

  const iteracoes = 100000;
  const salt = crypto.randomBytes(16);
  const hash = crypto.pbkdf2Sync(senha, salt, iteracoes, 32, 'sha256');

  const linha = `pbkdf2:${iteracoes}:${salt.toString('hex')}:${hash.toString('hex')}`;
  console.log('\nCopie esta linha inteira para o campo "senha_hash" do secrets.php:\n');
  console.log(linha);
})();
