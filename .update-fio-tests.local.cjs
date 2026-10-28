const fs=require('fs');const old=JSON.parse(fs.readFileSync('.fio-columns.local.json'));const current=JSON.parse(fs.readFileSync('wordpress/wp-content/plugins/tarhan-testing/data/organizer-columns.json'));
const col=n=>{let s='';for(n++;n;n=Math.floor((n-1)/26))s=String.fromCharCode(65+(n-1)%26)+s;return s;};
const map=Object.fromEntries(old.map((c,i)=>[col(i),col(current.findIndex(n=>n[0]===({last_name:'full_name',parent_last_name:'parent_full_name'}[c[0]]||c[0])))]));
let p='tests/verify-organizer-workbook.py',s=fs.readFileSync(p,'utf8');s=s.replace(/sheet\['([A-Z]+)(\d+)'\]/g,(m,c,r)=>`sheet['${map[c]}${r}']`).replace('sheet.max_column == 46','sheet.max_column == 42').replaceAll('A4:AT209','A4:AP209').replace("== 'Фамилия'","== 'ФИО участника'").replace("sheet['B5'].value == '=1+1'", "sheet['B5'].value.startswith('=1+1 Иван ')");fs.writeFileSync(p,s);
p='tests/verify-organizers.php';s=fs.readFileSync(p,'utf8').replaceAll('A4:AT209','A4:AP209');fs.writeFileSync(p,s);
