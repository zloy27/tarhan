const fs = require('fs');
const paths = ['src/html/components/registration/registration.html','wordpress/wp-content/themes/tarhan/participant-forms/registration.php'];
const group = '<fieldset class="registration__group"><legend><span>05</span> Преподаватель</legend><div class="registration__fields"><label class="registration__field"><span>ФИО преподавателя *</span><input name="teacher_name" type="text" required maxlength="250"></label><label class="registration__field"><span>Телефон преподавателя *</span><input name="teacher_phone" type="tel" required maxlength="30" placeholder="+7 (___) ___-__-__"></label></div></fieldset>\n';
for (const p of paths) {
 let s=fs.readFileSync(p,'utf8');
 s=s.replaceAll('<span>06</span>','<span>07</span>').replaceAll('<span>05</span>','<span>06</span>');
 const marker=p.endsWith('.php') ? '<?php if (is_user_logged_in())' : '<fieldset class="registration__group"><legend><span>06</span>';
 const pos=s.indexOf(marker); if(pos<0) throw new Error('Missing registration marker '+p);
 s=s.slice(0,pos)+group+s.slice(pos); fs.writeFileSync(p,s);
}
const p='src/html/components/information/contacts.html'; let s=fs.readFileSync(p,'utf8');
const start=s.indexOf('            <div class="contacts__info">',s.indexOf('Написать организаторам'));
if(start<0) throw new Error('Missing contacts marker');
s=s.slice(0,start)+['kfu','minkult'].map((name,i)=>`            <div class="contacts__info contacts__info--organization"><img class="contacts__organization-logo" src="/src/assets/photo/header/${name}-logo.png" alt="${i?'Министерство культуры Республики Татарстан':'Казанский федеральный университет'}"></div>`).join('\n')+'\n        </div>\n    </div>\n</section>\n'; fs.writeFileSync(p,s);
