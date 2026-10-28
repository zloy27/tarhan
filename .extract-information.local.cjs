const fs=require('fs');
const html=fs.readFileSync('.participant-build/information.html','utf8');
const main=html.match(/<main\b[^>]*>[\s\S]*?<\/main>/);
if(!main) throw Error('Missing main');
fs.writeFileSync('wordpress/wp-content/themes/tarhan/content/information.html',main[0]+'\n');
