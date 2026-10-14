import re, sys, shutil, os, sqlite3
# uso: preparar_teste.py <projeto> <destino> <novo|antigo>
src, dst, schema_alvo = sys.argv[1], sys.argv[2], sys.argv[3]
if os.path.exists(dst):
    shutil.rmtree(dst)
shutil.copytree(src, dst)
s = open(os.path.join(src, 'database/schema.sql'), encoding='utf-8').read()
s = re.sub(r'CREATE DATABASE[^;]*;', '', s)
s = re.sub(r'USE \w+;', '', s)
s = re.sub(r'\) ENGINE=InnoDB;', ');', s)
s = s.replace('INT AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT')
s = re.sub(r"ENUM\([^)]*\)", 'TEXT', s)
s = re.sub(r'--[^\n]*', '', s)
if schema_alvo == 'antigo':  # simula banco criado antes das colunas novas
    s = s.replace("    cor VARCHAR(7) NOT NULL DEFAULT '#6e0f1a',\n", '')
    s = s.replace("    responsavel_nome VARCHAR(150) NULL,\n    telefone_responsavel VARCHAR(30) NULL,\n", '')
    s = s.replace("    tipo VARCHAR(20) NOT NULL DEFAULT 'trabalho',\n", '')
    import re as _r
    s = _r.sub(r"INSERT INTO equipes \(nome, cor, tipo\) VALUES[^;]*;", '', s)
    s = _r.sub(r"CREATE TABLE tentativas_login \([^;]*;", '', s)
    assert 'cor VARCHAR' not in s and 'responsavel_nome' not in s
    assert 'tipo VARCHAR' not in s and 'tentativas_login' not in s
db = os.path.join(dst, 'teste.sqlite')
c = sqlite3.connect(db)
c.executescript(s)
c.commit()
c.close()
open(os.path.join(dst, 'config/database.local.php'), 'w').write(
    "<?php\nreturn ['dsn' => 'sqlite:' . __DIR__ . '/../teste.sqlite'];\n")
print('ok', schema_alvo)
