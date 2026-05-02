const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const productServicePath = path.join(root, 'src/Service/ProductService.php');
const importServicePath = path.join(root, 'src/Service/Imports/ProductImportService.php');

const productServiceSource = fs.readFileSync(productServicePath, 'utf8');
const importServiceSource = fs.readFileSync(importServicePath, 'utf8');

const requiredFieldMatches = [
  ...productServiceSource.matchAll(
    /\$this->hasValue\(\$data\['([^']+)'\] \?\? null\)\) {\s*throw new \\InvalidArgumentException\('([^']+)'\);/g,
  ),
];
const requiredFieldsFromValidation = [
  ...new Set(
    requiredFieldMatches
      .filter((match) => /obrigatorio\.$/.test(match[2]))
      .map((match) => match[1]),
  ),
].sort();

const requiredHeadersBlockMatch = importServiceSource.match(
  /private const REQUIRED_CSV_HEADERS = \[(.*?)\];/s,
);

if (!requiredHeadersBlockMatch) {
  throw new Error('Constante REQUIRED_CSV_HEADERS nao encontrada.');
}

const requiredHeaders = [
  ...requiredHeadersBlockMatch[1].matchAll(/'([^']+)'/g),
].map((match) => match[1]).sort();

if (
  requiredFieldsFromValidation.length !== requiredHeaders.length ||
  requiredFieldsFromValidation.some((field, index) => field !== requiredHeaders[index])
) {
  throw new Error(
    `Cabecalhos obrigatorios divergentes. validateImportRow=${JSON.stringify(requiredFieldsFromValidation)} REQUIRED_CSV_HEADERS=${JSON.stringify(requiredHeaders)}`,
  );
}

if (!/\? \$header \. '\*'/.test(importServiceSource)) {
  throw new Error('A marcacao de obrigatoriedade com * nao foi encontrada em getExampleCsv.');
}

console.log(
  `Contrato de importacao verificado com sucesso: ${requiredHeaders.join(', ')}`,
);
