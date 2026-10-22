import { useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors } from '../../hooks/useThemeColors';

const faqs = [
  {
    question: "What technologies do you work with?",
    answer: "On the development side: JavaScript, React, Next.js, Node.js, Express.js, PHP, MySQL, and MongoDB. On the design side: Photoshop, Illustrator, Canva, Figma, and WordPress."
  },
  {
    question: "Do you take on freelance projects?",
    answer: "Yes! I’m currently open to freelance web development and graphic design work — from full websites to smaller design tasks."
  },
  {
    question: "Do you offer graphic design services separately from development?",
    answer: "Absolutely. Branding, social media design, and UI/UX design are all available on their own, or bundled with a development project."
  },
  {
    question: "What is your typical turnaround time?",
    answer: "It depends on the scope of the project. I’ll always give a clear timeline up front after we talk through your requirements — reach out via the Contact page to discuss."
  },
  {
    question: "How can I download your CV?",
    answer: 'Use the "Download CV" button in the Resume section, or the "Resume" button at the top of the page — both link directly to my up-to-date CV.'
  },
  {
    question: "Are you open to remote work?",
    answer: "Yes. I’m based in Mogadishu, Somalia, and open to remote opportunities and collaborations worldwide."
  }
];

const FAQ = () => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();
  const [openIndex, setOpenIndex] = useState<number | null>(0);

  return (
    <section
      id="faq"
      className="py-20 relative"
      style={{
        background: themeColors.background.sections?.faq || themeColors.background.gradient,
        transition: 'background 0.3s ease-in-out'
      }}
    >
      <div className="container mx-auto px-6 relative" style={{ zIndex: 2 }}>
        <h2 className="text-4xl font-bold text-center mb-2" style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.pink[500] }}>
          Frequently Asked Questions
        </h2>
        <p className="text-center mb-12 text-sm" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}>
          Answers to common questions
        </p>
        <div className="max-w-2xl mx-auto space-y-3">
          {faqs.map((faq, index) => {
            const isOpen = openIndex === index;
            return (
              <div
                key={faq.question}
                className="rounded-xl overflow-hidden transition-colors duration-300"
                style={{
                  backgroundColor: themeColors.card.background,
                  border: `1px solid ${themeColors.card.border}`
                }}
              >
                <button
                  onClick={() => setOpenIndex(isOpen ? null : index)}
                  className="w-full flex items-center justify-between gap-4 text-left px-5 py-4 focus:outline-none bg-transparent border-none cursor-pointer"
                  style={{ minHeight: '44px', borderRadius: 0 }}
                  aria-expanded={isOpen}
                >
                  <span className="text-sm md:text-base font-semibold" style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.dark[700] }}>
                    {faq.question}
                  </span>
                  <ChevronDown
                    className="h-5 w-5 flex-shrink-0 transition-transform duration-300"
                    style={{
                      color: themeColors.colors.pink[500],
                      transform: isOpen ? 'rotate(180deg)' : 'rotate(0deg)'
                    }}
                  />
                </button>
                <div
                  className="grid transition-all duration-300 ease-in-out"
                  style={{ gridTemplateRows: isOpen ? '1fr' : '0fr' }}
                >
                  <div className="overflow-hidden">
                    <p className="text-sm leading-relaxed px-5 pb-4" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}>
                      {faq.answer}
                    </p>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
};

export default FAQ;
