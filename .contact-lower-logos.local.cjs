const fs=require('fs'); const p='src/html/components/information/contacts.html'; let s=fs.readFileSync(p,'utf8');
const headings=[...s.matchAll(/<div class="contacts__info--heading">[\s\S]*?<\/div>/g)].map(m=>m[0]);
let n=0;
s=s.replace(/<ul class="contacts__list">(?:(?!<\/ul>)[\s\S])*<\/ul>\s*<p class="contacts__info--note">Контакты добавим после подтверждения<\/p>/g, block=>{
 if(block.includes('contacts__button')) throw Error('Unexpected match');
 const name=['kfu','minkult'][n++]; return `<div class="contacts__organization"><img src="/src/assets/photo/header/${name}-logo.png" alt="${name==='kfu'?'Казанский федеральный университет':'Министерство культуры Республики Татарстан'}" class="contacts__organization-logo"></div>`;
});
if(n!==2) throw Error('Expected two blocks');
if(headings.some(h=>!s.includes(h))) throw Error('Heading changed');
fs.writeFileSync(p,s);
