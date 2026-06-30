/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        "./templates/**/*.html.twig",
        "./assets/**/*.js",
        "./assets/**/*.ts",
        "./assets/**/*.scss",
    ],
    safelist: [
        // Couleurs utilisées dynamiquement dans le comparatif produits
        // verts
        'bg-green-50','bg-green-100','bg-green-200','text-green-600',
        // bleus
        'bg-blue-50','bg-blue-100','bg-blue-200','text-blue-600',
        // violets
        'bg-purple-50','bg-purple-100','bg-purple-200','text-purple-600',
        // oranges
        'bg-orange-50','bg-orange-100','bg-orange-200','text-orange-600',
        // Dashboard premium grid layout
        'xl:grid-cols-5','xl:col-span-3','xl:col-span-2',
        'grid-cols-1','gap-6',
    ],
    darkMode: 'class',
    theme: {
        container: {
            center: true,
        },
        extend: {
            colors: {
                homblue: {
                    light: '#EFF2FF',
                    normal: '#061556',
                    alternate: '#2037B3',
                },
                homgreen: {
                    light: '#D1F6EC',
                    normal: '#6DDBC4',
                    alternate: '#3FC2AB',
                },
                red: {
                    600: '#D7385E'
                }
            },
            fontFamily: {
                Rubik: ['Rubik', 'Helvetica Neue', 'Helvetica', 'Arial', 'sans-serif'],
                Jakarta: ['Plus Jakarta Sans', 'sans-serif'],
                Inter: ['Inter', 'sans-serif'],
                Flaticon: ['Flaticon'],
                fontawesome: ['FontAwesome', 'sans-serif'],
                ofelia: ['Ofelia Display', 'Helvetica', 'Arial', 'sans-serif'],
            },
            boxShadow: {
                pop: '0 0px 15px 0px rgb(0 0 0 / 0.15)'
            },
            borderWidth: {
                3: '3px'
            },
            rotate: {
                135: '135deg'
            },

            height: {
                player: '24rem',
                image: '32.25rem'
            },
            width: {
                image: '32.25rem',
                player: '42.5rem',
                128: '36rem'
            },
            fontSize: {
                '2xl': '1.563rem',
                '3xl': '1.953rem'
            }
        },
        screens: {
            'xxl': {'min': '1400px'},
            'xl': {'min': '1200px'},
            'lg': {'min': '992px', 'max': '1199.98px'},
            'md': {'min': '768px', 'max': '991.98px'},
            'sm': {'min': '480px', 'max': '767.98px'},
            'xsm': {'min': '414px', 'max': '479.98px'},
        }
    },
    plugins: [
        require('@tailwindcss/forms'),
        function ({addComponents}) {
            addComponents({
                '.container': {
                    maxWidth: '100%',
                    '@screen sm': {maxWidth: '540px'},
                    '@screen md': {maxWidth: '720px'},
                    '@screen lg': {maxWidth: '960px'},
                    '@screen xl': {maxWidth: '1140px'},
                    '@screen xxl': {maxWidth: '1320px'},
                }
            })
        }
    ],
}
