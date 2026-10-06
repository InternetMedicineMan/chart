<script setup>
import Hero from "@/Components/Hero.vue";
import Partners from "@/Components/Partners.vue";
import ContentWithImage from "@/Components/ContentWithImage.vue";
import Plans from "@/Components/Plans.vue";
import Faq from "@/Components/Faq.vue";
import CTA from "@/Components/CTA.vue";
import HomeLayout from "@/Layouts/HomeLayout.vue";
import PaddlePlans from "@/Components/Paddle/PaddlePlans.vue";
import Features from "@/Components/Features.vue";
import AboutMe from "@/Components/AboutMe.vue";
import FeaturesFlow from "@/Components/FeaturesFlow.vue";
import Integrations from "@/Components/Integrations.vue";
import Problem from "@/Components/Problem.vue";
defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

</script>

<template >
    <HomeLayout>
        <Hero/>
        <Partners/>
        <FeaturesFlow/>
        <Integrations/>
        <ContentWithImage/>
        <Problem
            heading="Empower Your Workflow: Unleashing the Full Potential of SaaS Integrations"
            text="In today’s fast-paced digital landscape, integrating the right tools can make or break your productivity. With our cutting-edge SaaS platform, you’re not just adopting software—you’re transforming how your team collaborates, manages resources, and achieves success. Explore the possibilities and take your operations to the next level."
            :steps="[
                { emoji: '🤔', text: 'Connect Your Tools: Effortlessly integrate with key platforms to centralize your workflow.' },
                { emoji: '🚀', text: 'Automate Processes: Set up automated tasks that save time and reduce manual effort.' },
                { emoji: '🎉', text: 'Gain Insights: Leverage powerful analytics to make data-driven decisions.' },
            ]"/>
<!--        for Paddle-->
<!--        <PaddlePlans/>-->
        <Features/>
        <AboutMe/>
        <Plans/>
        <Faq/>
        <CTA/>
    </HomeLayout>
</template>

<style>
.bg-dots-darker {
    background-image: url("data:image/svg+xml,%3Csvg width='30' height='30' viewBox='0 0 30 30' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1.22676 0C1.91374 0 2.45351 0.539773 2.45351 1.22676C2.45351 1.91374 1.91374 2.45351 1.22676 2.45351C0.539773 2.45351 0 1.91374 0 1.22676C0 0.539773 0.539773 0 1.22676 0Z' fill='rgba(0,0,0,0.07)'/%3E%3C/svg%3E");
}
@media (prefers-color-scheme: dark) {
    .dark\:bg-dots-lighter {
        background-image: url("data:image/svg+xml,%3Csvg width='30' height='30' viewBox='0 0 30 30' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1.22676 0C1.91374 0 2.45351 0.539773 2.45351 1.22676C2.45351 1.91374 1.91374 2.45351 1.22676 2.45351C0.539773 2.45351 0 1.91374 0 1.22676C0 0.539773 0.539773 0 1.22676 0Z' fill='rgba(255,255,255,0.07)'/%3E%3C/svg%3E");
    }
}
</style>
