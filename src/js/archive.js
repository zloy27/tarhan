
import '../scss/archive.scss';
import { initPageNavigation } from './components/page-navigation.js';

initPageNavigation();

var cards = [...document.querySelectorAll(".seasons__card")]
var cards__queue = []
// Создание обьекта для наблюдения -> поставить наблюдатель на объект->
//->перенос в очередь-> изьятие из очереди, конец наблюдения -> 
// -> если очередь завершена завершить код

var observer = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            // entry.target.classList.add("seasons__card--visible");
            cards__queue.push(entry.target)
            cards__queue.forEach((card, i) => {
                setTimeout(() => {
                    card.classList.add("seasons__card--visible");
                    observer.unobserve(card)
                }, 200 * i)
            })
        }
    })
})

cards.forEach(card => {
    observer.observe(card)
})
// следущая функция может быть запущена даже если функция выше не была закончена


