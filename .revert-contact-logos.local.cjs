const fs=require('fs');
const file='src/html/components/information/contacts.html';
let s=fs.readFileSync(file,'utf8');
for(const [name,label,title] of [['kfu','КФУ','Казанский федеральный университет'],['minkult','Минкультуры РТ','Министерство культуры Республики Татарстан']]) {
 const old=`            <div class="contacts__info contacts__info--organization"><img class="contacts__organization-logo" src="/src/assets/photo/header/${name}-logo.png" alt="${title}"></div>`;
 if(!s.includes(old)) throw Error('Card changed: '+name);
 const items=[['phone','Телефон','Контакт уточняется'],['mail','Электронная почта','Контакт уточняется'],['location','Адрес','Адрес уточняется']].map(([icon,title,text])=>`                    <li class="contacts__list--item"><img src="/src/assets/icons/information/${icon}.svg" width="26" height="26" alt="" aria-hidden="true"><p class="contacts__list--placeholder">${title}<br>${text}</p></li>`).join('\n');
 s=s.replace(old,`            <div class="contacts__info">\n                <div class="contacts__info--heading"><span class="contacts__info--label">${label}</span><h2 class="contacts__info--title">${title}</h2></div>\n                <ul class="contacts__list">\n${items}\n                </ul>\n                <p class="contacts__info--note">Контакты добавим после подтверждения</p>\n            </div>`);
}
fs.writeFileSync(file,s);
const css='src/scss/components/information/contacts.scss';
s=fs.readFileSync(css,'utf8'); s=s.replace(/    &__info--organization \{[\s\S]*?(?=    &__list \{)/,''); fs.writeFileSync(css,s);
fs.copyFileSync('.contacts-backup-20261026-211258.local/themes/tarhan/content/information.html','wordpress/wp-content/themes/tarhan/content/information.html');
