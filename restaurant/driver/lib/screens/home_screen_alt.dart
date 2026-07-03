import 'package:flutter/material.dart';
import '../widgets/restaurant_card_alt.dart';

class HomeScreenAlt extends StatelessWidget {
  const HomeScreenAlt({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            // Top Location Picker and Profile
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Row(
                  children: [
                    const Icon(Icons.location_on, color: Color(0xFFFC8019), size: 28),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: const [
                              Text(
                                'Home',
                                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                              ),
                              Icon(Icons.keyboard_arrow_down),
                            ],
                          ),
                          const Text(
                            '123, Tech Park, Bangalore, Karnataka',
                            style: TextStyle(color: Colors.grey, fontSize: 12),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                    const CircleAvatar(
                      backgroundColor: Colors.grey,
                      child: Icon(Icons.person, color: Colors.white),
                    ),
                  ],
                ),
              ),
            ),

            // Sticky Search Bar
            SliverAppBar(
              pinned: true,
              floating: true,
              backgroundColor: Colors.white,
              elevation: 0,
              automaticallyImplyLeading: false,
              title: Container(
                height: 50,
                decoration: BoxDecoration(
                  color: Colors.grey[100],
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.grey[300]!),
                ),
                child: TextField(
                  decoration: InputDecoration(
                    hintText: 'Search for restaurant, item or more',
                    prefixIcon: const Icon(Icons.search, color: Colors.grey),
                    suffixIcon: const Icon(Icons.mic, color: Color(0xFFFC8019)),
                    border: InputBorder.none,
                    contentPadding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
            ),

            // Promotional Banners
            SliverToBoxAdapter(
              child: SizedBox(
                height: 200,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
                  itemCount: 3,
                  itemBuilder: (context, index) => Container(
                    width: 300,
                    margin: const EdgeInsets.only(right: 16),
                    decoration: BoxDecoration(
                      color: Colors.orange[100 * (index + 1)],
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Center(child: Text('Special Offer ${index + 1}')),
                  ),
                ),
              ),
            ),

            // Categories Grid (What's on your mind?)
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.symmetric(horizontal: 16.0),
                child: Text(
                  "What's on your mind?",
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
              ),
            ),

            SliverToBoxAdapter(
              child: SizedBox(
                height: 120,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.all(16),
                  itemCount: 6,
                  itemBuilder: (context, index) => Padding(
                    padding: const EdgeInsets.only(right: 20),
                    child: Column(
                      children: [
                        CircleAvatar(
                          radius: 30,
                          backgroundColor: Colors.grey[200],
                          child: const Icon(Icons.fastfood, color: Colors.orange),
                        ),
                        const SizedBox(height: 8),
                        const Text('Item'),
                      ],
                    ),
                  ),
                ),
              ),
            ),

            const SliverPadding(
              padding: EdgeInsets.all(16),
              sliver: SliverToBoxAdapter(
                child: Divider(),
              ),
            ),

            // Restaurant List
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.symmetric(horizontal: 16.0, vertical: 8),
                child: Text(
                  "85 restaurants to explore",
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
              ),
            ),

            SliverList(
              delegate: SliverChildBuilderDelegate(
                (context, index) => const RestaurantCardAlt(
                  name: "The Gourmet Kitchen",
                  cuisine: "North Indian, Chinese",
                  rating: "4.2",
                  deliveryTime: "30-35 mins",
                ),
                childCount: 10,
              ),
            ),
          ],
        ),
      ),
    );
  }
}