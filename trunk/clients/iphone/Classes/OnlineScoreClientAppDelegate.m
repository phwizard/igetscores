//
//  OnlineScoreClientAppDelegate.m
//  OnlineScoreClient
//
//  Created by Andrew Kopanev on 6/4/09.
//  Copyright 2009 Injoit.com. All rights reserved.
//

#import "OnlineScoreClientAppDelegate.h"
#import "OnlineScore.h"

@implementation OnlineScoreClientAppDelegate

@synthesize window;


- (void)applicationDidFinishLaunching:(UIApplication *)application {    
	[[OnlineScore getInstance] setConsumerKey: @"somekey" andSecret: @"somesecret"];
	[[OnlineScore getInstance] getAccessTokenWithDelegate: self callbackSelector: @selector(gotAccesToken:)];

    // Override point for customization after application launch
    [window makeKeyAndVisible];
}

- (void) gotAccesToken: (id) token {
	if (token) {
		NSLog(@"access granted!");
		
		[[OnlineScore getInstance] addScore: [NSDictionary dictionaryWithObjectsAndKeys: 
											  @"1", @"subgame_id",
											  @"1000" , @"value",
											  @"test", @"name",
											  @"15", @"limit_below",
											  @"5", @"limit_above",
											  nil]
								   delegate: self
						   callbackSelector: @selector(scoreAdded:forOperation:) 
					   callbackFailSelector: @selector(scoresFail:forOperation:)];
		
	} else {
		NSLog(@"wrong key / secret pair, imho");
	}
}

- (void) scoreAdded: (NSDictionary*) dict forOperation: (OnlineScoreOperation*) op {
	NSLog(@"score added callback selector");
}

- (void) gotScore: (NSDictionary*) dict forOperation: (OnlineScoreOperation*) op {
	
}

- (void) scoresFail: (id) dict forOperation: (OnlineScoreOperation*) op {
	NSLog(@"score operation failed");
	
}


- (void)dealloc {
    [window release];
    [super dealloc];
}


@end
