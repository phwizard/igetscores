//
//  OnlineScoreOperation.m
//
//  Created by Andrew Kopanev on 4/8/09.
//  Copyright 2009 Injoit.com. All rights reserved.
//

#import "OnlineScoreOperation.h"


@implementation OnlineScoreOperation

+ (id) queueDataRequest: (OAMutableURLRequest*) aRequest 
					   withIdentifier:(NSInteger)anIdentifier withCallbackTarget:(id)aTarget 
		   withCallbackFinishSelector:(SEL)aSelector withCallbackFailSelector: (SEL) aFailSelector {
	OnlineScoreOperation *thisDataLoaderOperation = [[OnlineScoreOperation alloc] init]; // autorelease];
	
	thisDataLoaderOperation->callbackTarget = aTarget;
	thisDataLoaderOperation->callbackFinishSelector = aSelector;
	thisDataLoaderOperation->callbackFailSelector = aFailSelector;
	thisDataLoaderOperation->identifier = anIdentifier;
	thisDataLoaderOperation->request = aRequest;
	
	return thisDataLoaderOperation;
}

-(void) didFinishSelector: (OAServiceTicket*) ticket withData: (NSData*) data {
	
	NSString *str = [[NSString alloc] initWithBytes: [data bytes] length: [data length] encoding: NSUTF8StringEncoding];
	NSLog(@"xml: %@", str);
	
	if (ticket.didSucceed) {		
		XMLTreeParser *parser = [[XMLTreeParser alloc] init];
		XMLTreeNode* rootNode = [parser parse: data];

		if (rootNode) {
			if ([callbackTarget respondsToSelector: callbackFinishSelector]) {
				[callbackTarget performSelector: callbackFinishSelector withObject: rootNode withObject: self];
			}
		} else {
			if ([callbackTarget respondsToSelector: callbackFailSelector]) {
				[callbackTarget performSelector: callbackFailSelector withObject: nil withObject: self];
			}		
		}
		[parser release];
	} else {
		if ([callbackTarget respondsToSelector: callbackFailSelector]) {
			[callbackTarget performSelector: callbackFailSelector withObject: nil withObject: self];
		}		
	}
}

- (void) queryFailed: (OAServiceTicket *)ticket didFailWithError: (NSError*) error {
	if ([callbackTarget respondsToSelector: callbackFailSelector]) {
		[callbackTarget performSelector: callbackFailSelector withObject: nil withObject: self];
	}
}

- (void) main {
	OADataFetcher *fetcher = [[OADataFetcher alloc] init];
	[fetcher fetchDataWithRequest: request
						 delegate: self
				didFinishSelector: @selector(didFinishSelector:withData:)
				  didFailSelector: @selector(queryFailed:didFailWithError:)];
	[fetcher release];
}

@end
